<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Room;
use Illuminate\Support\Str;

/**
 * Monta o problema do resolvedor (RF05) para o semestre inteiro: professores atendem
 * vários cursos e turnos, então a grade só fecha olhando tudo junto.
 * O resultado é consumido pelo public/js/schedule-solver.js (Web Worker).
 */
class SolverProblemBuilder
{
    public function __construct(private ScheduleAllocator $allocator)
    {
    }

    public function build(AcademicTerm $term): array
    {
        $all = ClassOffering::with(['subject', 'slots', 'assignments', 'matrixDiscipline', 'term'])
            ->where('academic_term_id', $term->id)->where('occurs', true)->get();
        $isVariable = fn (ClassOffering $o) => ! $o->isDistance() && $o->classesToSchedule() > 0;
        $offerings = $all->filter($isVariable)->values();
        // O que ocupa a grade mas não será resolvido (EaD com horário, carga zero) entra como bloqueio fixo.
        $frozen = $all->reject($isVariable)->filter(fn (ClassOffering $o) => $o->slots->isNotEmpty())->values();

        $qualified = Professor::with('subjects:id')->where('active', true)->get();
        $rooms = Room::where('active', true)->orderBy('code')->get();

        $problemOfferings = $offerings->map(function (ClassOffering $o) use ($qualified, $rooms) {
            $assignment = $o->assignments->first();
            $current = $this->allocator->currentInput($o);
            $candidates = $qualified->filter(fn ($p) => $p->subjects->contains('id', $o->subject_id))->pluck('id')->all();
            if ($assignment && ! in_array($assignment->professor_id, $candidates, true)) {
                $candidates[] = $assignment->professor_id; // professor atual, mesmo sem habilitação registrada
            }
            $required = $o->matrixDiscipline?->required_room_type;

            return [
                'id' => $o->id,
                'course' => $o->course_id,
                'class' => $o->class_code,
                'shift' => $o->shift,
                'subject' => $o->subject->name,
                'abbr' => $o->subject->short_label,
                'subjectKey' => preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($o->subject->name))),
                'expected' => $o->classesToSchedule(),
                'directed' => (bool) $o->directed_study,
                'maxConsecutive' => (int) ($o->matrixDiscipline?->max_consecutive_classes ?: 2),
                'candidates' => array_values($candidates),
                'fixedProfessor' => $assignment && $assignment->status === 'confirmed' ? $assignment->professor_id : null,
                'professor' => $assignment?->professor_id,
                // Art. 8º III: prática só em laboratório apto; teórica fora dos laboratórios de informática.
                'rooms' => LabRules::eligibleRooms($o, $rooms)->pluck('code')->values()->all(),
                'room' => $current['room'],
                'placements' => $current['placements'],
                'frozen' => false,
            ];
        })->values();

        $problemOfferings = $problemOfferings->concat($frozen->map(function (ClassOffering $o) {
            $current = $this->allocator->currentInput($o);

            return [
                'id' => $o->id, 'course' => $o->course_id, 'class' => $o->class_code, 'shift' => $o->shift, 'subject' => $o->subject->name,
                'abbr' => $o->subject->short_label,
                'subjectKey' => preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($o->subject->name))),
                'expected' => array_sum(array_map('count', $current['placements'])),
                'directed' => (bool) $o->directed_study, 'maxConsecutive' => 99,
                'candidates' => array_values(array_filter([$current['professor_id']])),
                'fixedProfessor' => $current['professor_id'], 'professor' => $current['professor_id'],
                'rooms' => array_values(array_filter([$current['room']])), 'room' => $current['room'],
                'placements' => $current['placements'],
                'frozen' => true,
            ];
        }))->values();

        $professorIds = $problemOfferings->flatMap(fn ($o) => $o['candidates'])->unique()->values();
        $availability = ProfessorAvailability::where('academic_term_id', $term->id)->whereIn('professor_id', $professorIds)->get()->groupBy('professor_id');

        return [
            'term' => $term->code,
            'shifts' => collect(['MANHÃ', 'TARDE', 'NOITE'])->mapWithKeys(fn ($s) => [$s => collect(TimeBlocks::forShift($s))->map(fn ($slot) => $slot['start'])->all()])->all(),
            'weights' => ScheduleScorer::WEIGHTS + ['consecutive' => 3],
            'professors' => Professor::whereIn('id', $professorIds)->get()->map(fn (Professor $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'maxPerDay' => $p->max_classes_per_day,
                'maxDays' => $p->max_days_per_week,
                'hasGrid' => $availability->has($p->id),
                // "dia|HH:MM" => preferred | available | unavailable
                'availability' => ($availability->get($p->id) ?? collect())->mapWithKeys(fn ($a) => [$a->weekday . '|' . substr((string) $a->starts_at, 0, 5) => $a->preference])->all(),
            ])->values()->all(),
            'offerings' => $problemOfferings->all(),
        ];
    }
}
