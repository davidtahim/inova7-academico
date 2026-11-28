<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\ClassOffering;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\StudentSubjectStatus;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Enturmação dos alunos de uma matriz (ex.: SI 2019.1) no semestre: cada disciplina pendente
 * no histórico vai para a turma ofertada da mesma disciplina. Turmas com poucos alunos viram
 * Estudo Dirigido (regra da oferta 2026.1).
 */
class EnrollmentPlanner
{
    public const PENDING = ['falta_cursar', 'reprovada', 'trancada'];

    public function __construct(private ScheduleAllocator $allocator)
    {
    }

    /** @return Collection<int, User> */
    public function students(CurriculumMatrix $matrix): Collection
    {
        return User::where('role', 'student')->where('curriculum_matrix_id', $matrix->id)->orderBy('name')->get();
    }

    /**
     * Pendências por disciplina: demanda (pendência certa), "matriculada" sem resultado e turma no semestre.
     *
     * @return Collection<int, array{subject: Subject, period: ?int, pending: Collection, in_progress: Collection, offering: ?ClassOffering}>
     */
    public function demand(AcademicTerm $term, CurriculumMatrix $matrix): Collection
    {
        $studentIds = $this->students($matrix)->pluck('id');
        $statuses = StudentSubjectStatus::with(['subject', 'user'])->whereIn('user_id', $studentIds)->get()->groupBy('subject_id');
        $links = CurriculumMatrixDiscipline::with('subject')->where('curriculum_matrix_id', $matrix->id)->get();

        return $links->map(function (CurriculumMatrixDiscipline $link) use ($statuses, $term, $matrix) {
            $items = $statuses->get($link->subject_id, collect());

            return [
                'subject' => $link->subject,
                'period' => $link->period,
                'pending' => $items->whereIn('status', self::PENDING)->pluck('user')->values(),
                'in_progress' => $items->where('status', 'matriculada')->pluck('user')->values(),
                'offering' => $this->offeringFor($term, $matrix, $link->subject_id, null),
            ];
        })->sortBy(fn ($row) => [$row['period'], $row['subject']->name])->values();
    }

    /**
     * Gera (ou refaz) a enturmação sugerida. Enturmações confirmadas são mantidas.
     *
     * @return array{students: int, enrolled: int, without_offering: array<string, int>, offerings_updated: int}
     */
    public function plan(AcademicTerm $term, CurriculumMatrix $matrix, bool $includeInProgress = false): array
    {
        $pendingStatuses = $includeInProgress ? [...self::PENDING, 'matriculada'] : self::PENDING;
        $students = $this->students($matrix);
        $report = ['students' => $students->count(), 'enrolled' => 0, 'without_offering' => [], 'offerings_updated' => 0];
        $touched = [];

        DB::transaction(function () use ($term, $matrix, $students, $pendingStatuses, &$report, &$touched) {
            foreach ($students as $student) {
                $planned = [];
                $pending = StudentSubjectStatus::with('subject')->where('user_id', $student->id)->whereIn('status', $pendingStatuses)->get();

                foreach ($pending as $status) {
                    $offering = $this->offeringFor($term, $matrix, $status->subject_id, $student->shift);
                    if (! $offering) {
                        $report['without_offering'][$status->subject->name] = ($report['without_offering'][$status->subject->name] ?? 0) + 1;
                        continue;
                    }
                    ClassEnrollment::firstOrCreate(['user_id' => $student->id, 'class_offering_id' => $offering->id], ['status' => 'planned']);
                    $planned[] = $offering->id;
                    $touched[$offering->id] = true;
                    $report['enrolled']++;
                }

                // Sugestões antigas que deixaram de valer neste semestre (as confirmadas ficam).
                $stale = ClassEnrollment::where('user_id', $student->id)->where('status', 'planned')
                    ->whereNotIn('class_offering_id', $planned)
                    ->whereHas('offering', fn ($q) => $q->where('academic_term_id', $term->id));
                foreach ((clone $stale)->pluck('class_offering_id') as $offeringId) {
                    $touched[$offeringId] = true;
                }
                $stale->delete();
            }

            foreach (array_keys($touched) as $offeringId) {
                $this->refreshLoad(ClassOffering::with('matrixDiscipline')->find($offeringId));
                $report['offerings_updated']++;
            }
        });

        arsort($report['without_offering']);

        return $report;
    }

    /** Abre uma turma de Estudo Dirigido para a disciplina da matriz (código no padrão CSE02801PPNNB). */
    public function openDirectedStudyClass(AcademicTerm $term, CurriculumMatrix $matrix, Subject $subject, string $shift = 'NOITE'): ClassOffering
    {
        $link = CurriculumMatrixDiscipline::where('curriculum_matrix_id', $matrix->id)->where('subject_id', $subject->id)->firstOrFail();
        return ClassOffering::firstOrCreate(
            ['academic_term_id' => $term->id, 'class_code' => $this->classCode($link->period, $shift), 'subject_id' => $subject->id],
            [
                'course_id' => $matrix->course_id,
                'curriculum_matrix_id' => $matrix->id,
                'curriculum_matrix_discipline_id' => $link->id,
                'period' => $link->period,
                'shift' => $shift,
                'modality' => 'PRESENCIAL',
                'directed_study' => true,
                'occurs' => true,
                'weekly_hours' => 1,
                'totvs_hours' => 1,
                'status' => 'planned',
            ]
        );
    }

    /** Turmas em que o aluno foi enturmado no semestre. */
    public function enrolledOfferings(User $student, AcademicTerm $term): Collection
    {
        return ClassOffering::with(['subject', 'slots', 'assignments.professor'])
            ->where('academic_term_id', $term->id)
            ->whereHas('enrollments', fn ($q) => $q->where('user_id', $student->id))
            ->get();
    }

    /**
     * Sobreposições na grade pessoal do aluno (duas disciplinas no mesmo dia/horário).
     *
     * @return array<int, string> ex.: "Qua 18:30: Banco de Dados II × Sistemas Distribuídos"
     */
    public function studentClashes(User $student, AcademicTerm $term): array
    {
        $slots = $this->enrolledOfferings($student, $term)->flatMap(fn (ClassOffering $o) => $o->slots->map(fn ($slot) => [
            'subject' => $o->subject->name, 'weekday' => (int) $slot->weekday, 'start' => substr($slot->starts_at, 0, 5), 'end' => substr($slot->ends_at, 0, 5),
        ]))->values();

        $clashes = [];
        foreach ($slots as $i => $a) {
            foreach ($slots->slice($i + 1) as $b) {
                if ($a['subject'] !== $b['subject'] && $a['weekday'] === $b['weekday'] && $a['start'] < $b['end'] && $b['start'] < $a['end']) {
                    $pair = [$a['subject'], $b['subject']];
                    sort($pair);
                    $clashes[] = TimeBlocks::SHORT_WEEKDAYS[$a['weekday']] . ' ' . max($a['start'], $b['start']) . ': ' . implode(' × ', $pair);
                }
            }
        }

        return array_values(array_unique($clashes));
    }

    private function refreshLoad(?ClassOffering $offering): void
    {
        if (! $offering) {
            return;
        }
        $count = $offering->enrollments()->count();
        $suggestion = $this->allocator->suggestLoad($offering, $count);
        $offering->update(['student_count' => $count] + $suggestion);
    }

    private function offeringFor(AcademicTerm $term, CurriculumMatrix $matrix, int $subjectId, ?string $shift): ?ClassOffering
    {
        $candidates = ClassOffering::where('academic_term_id', $term->id)->where('subject_id', $subjectId)->where('occurs', true)->get();

        return $candidates->first(fn ($o) => (int) $o->curriculum_matrix_id === (int) $matrix->id && $shift && $o->shift === $shift)
            ?? $candidates->first(fn ($o) => (int) $o->curriculum_matrix_id === (int) $matrix->id)
            ?? $candidates->first();
    }

    private function classCode(int $period, string $shift): string
    {
        // Padrão das turmas da matriz antiga: CSE0280103NNB (3º período, noite, turma B).
        $shiftLetter = match ($shift) {
            'MANHÃ' => 'M',
            'TARDE' => 'T',
            default => 'N',
        };

        return sprintf('CSE02801%02dN%sB', $period, $shiftLetter);
    }
}
