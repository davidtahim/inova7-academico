<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;

/**
 * Dados do painel do professor no semestre: turmas alocadas, indicadores e
 * checklist de prontidão (dados funcionais, habilitação, disponibilidade).
 */
class TeacherDashboard
{
    public const QUALIFICATIONS = ['Graduado', 'Especialista', 'Mestre', 'Doutor', 'Pós-Doutor'];

    public const ASSIGNMENT_STATUS = [
        'planned' => ['label' => 'Sugerida', 'class' => 'bg-warning-subtle text-warning-emphasis'],
        'pending_confirmation' => ['label' => 'Aguardando confirmação', 'class' => 'bg-info-subtle text-info-emphasis'],
        'confirmed' => ['label' => 'Confirmada', 'class' => 'bg-success-subtle text-success-emphasis'],
    ];

    public const WEEKDAYS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

    public function build(Professor $professor, ?AcademicTerm $term): array
    {
        $assignments = $term
            ? TeachingAssignment::query()
                ->where('professor_id', $professor->id)
                ->whereHas('offering', fn ($query) => $query->where('academic_term_id', $term->id))
                ->with(['offering.subject', 'offering.course', 'offering.slots', 'offering.matrixDiscipline'])
                ->get()
                ->sortBy(fn (TeachingAssignment $a) => [$a->offering->course->name ?? '', $a->offering->period, $a->offering->subject->name ?? ''])
                ->values()
            : collect();

        $qualifiedIds = $professor->subjects()->pluck('subjects.id')->map(fn ($id) => (int) $id)->all();

        $availabilityCount = $term
            ? ProfessorAvailability::where('professor_id', $professor->id)
                ->where('academic_term_id', $term->id)
                ->where('preference', '!=', 'unavailable')
                ->count()
            : 0;

        $missingFields = $this->missingProfessionalFields($professor);

        return [
            'assignments' => $assignments,
            'qualifiedIds' => $qualifiedIds,
            'stats' => $this->stats($assignments),
            'missingFields' => $missingFields,
            'checklist' => [
                'professional' => ['done' => $missingFields === [], 'detail' => $missingFields === [] ? 'Completo' : 'Falta: ' . implode(', ', $missingFields)],
                'qualification' => ['done' => $qualifiedIds !== [], 'detail' => count($qualifiedIds) . ' disciplina(s) habilitada(s)'],
                'availability' => ['done' => $availabilityCount > 0, 'detail' => $availabilityCount . ' horário(s) disponível(is)'],
            ],
        ];
    }

    /** @return array<int, string> rótulos dos campos obrigatórios ainda não informados */
    public function missingProfessionalFields(Professor $professor): array
    {
        $missing = [];
        if (blank($professor->registration)) {
            $missing[] = 'Matrícula';
        }
        if (blank($professor->chapa)) {
            $missing[] = 'Chapa';
        }
        if (! in_array($professor->qualification, self::QUALIFICATIONS, true)) {
            $missing[] = 'Formação';
        }

        return $missing;
    }

    private function stats(Collection $assignments): array
    {
        $offerings = $assignments->pluck('offering');

        return [
            'classes' => $assignments->count(),
            'weekly_hours' => (float) $assignments->sum(fn (TeachingAssignment $a) => (float) ($a->weekly_hours ?: $a->offering->weekly_hours)),
            'students' => (int) $offerings->sum('student_count'),
            'confirmed' => $assignments->where('status', 'confirmed')->count(),
        ];
    }
}
