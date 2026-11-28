<?php

namespace App\Services;

use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * Monta o catálogo de disciplinas para a habilitação docente (CCG-FOR-02):
 * disciplinas das matrizes curriculares ativas, agrupadas por curso → matriz → período,
 * e as demais disciplinas cadastradas que não estão em nenhuma matriz ativa.
 */
class TeachingQualificationCatalog
{
    /** Status de matriz considerados vigentes para oferta. */
    public const ACTIVE_MATRIX_STATUSES = ['Atual', 'Ativa'];

    /**
     * @return array{
     *     matrices: Collection<int, array{matrix: CurriculumMatrix, periods: Collection<int, Collection<int, CurriculumMatrixDiscipline>>, subject_ids: array<int>}>,
     *     others: Collection<int, Subject>,
     *     total_subjects: int
     * }
     */
    /**
     * @param  array<int>|null  $courseIds  só as matrizes destes cursos (cursos do professor); null = todos
     * @param  array<int>  $selectedIds  disciplinas já marcadas: com filtro, as de fora dos cursos continuam visíveis
     */
    public function build(?array $courseIds = null, array $selectedIds = []): array
    {
        $matrices = CurriculumMatrix::query()
            ->whereIn('status', self::ACTIVE_MATRIX_STATUSES)
            ->when($courseIds, fn ($query) => $query->whereIn('course_id', $courseIds))
            ->whereHas('course', fn ($query) => $query->where('active', true))
            ->whereHas('disciplinesForOffering')
            ->with([
                'course',
                'disciplinesForOffering' => fn ($query) => $query->orderBy('period'),
                'disciplinesForOffering.subject',
            ])
            ->get()
            ->sortBy(fn (CurriculumMatrix $matrix) => [$matrix->course->name, $matrix->code])
            ->values();

        $groups = $matrices->map(function (CurriculumMatrix $matrix) {
            $links = $matrix->disciplinesForOffering
                ->filter(fn (CurriculumMatrixDiscipline $link) => $link->subject !== null)
                ->sortBy(fn (CurriculumMatrixDiscipline $link) => [$link->period, $link->subject->name]);

            return [
                'matrix' => $matrix,
                'periods' => $links->groupBy('period')->sortKeys(),
                'subject_ids' => $links->pluck('subject_id')->unique()->values()->all(),
            ];
        });

        $inMatrices = $groups->flatMap(fn (array $group) => $group['subject_ids'])->unique();

        $others = Subject::query()
            ->whereNotIn('id', $inMatrices->all())
            // Com filtro de cursos, "outras" são só as já marcadas (para poder desmarcar).
            ->when($courseIds, fn ($query) => $query->whereIn('id', $selectedIds ?: [0]))
            ->orderBy('name')
            ->get();

        return [
            'matrices' => $groups,
            'others' => $others,
            'total_subjects' => $inMatrices->count() + $others->count(),
        ];
    }

    /**
     * Carga presencial (teórica + prática presencial). Disciplinas 100% EaD não ocupam sala física.
     */
    public static function isFullyOnline(Subject $subject): bool
    {
        return (int) $subject->presential_hours === 0
            && (int) $subject->practice_hours === 0
            && (int) $subject->online_hours > 0;
    }
}
