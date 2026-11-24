<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CurriculumMatrixDiscipline extends Model
{
    protected $table = 'curriculum_matrix_discipline';

    protected $fillable = [
        'curriculum_matrix_id',
        'subject_id',
        'period',
        'shift',
        'weekly_hours',
        'offering_type',
        'allows_shared_class',
        'required_room_type',
        'max_consecutive_classes',
    ];

    protected $casts = [
        'allows_shared_class' => 'boolean',
        'weekly_hours' => 'integer',
        'period' => 'integer',
        'max_consecutive_classes' => 'integer',
    ];

    /**
     * Obter a matriz curricular associada.
     */
    public function curriculumMatrix(): BelongsTo
    {
        return $this->belongsTo(CurriculumMatrix::class);
    }

    /**
     * Obter a disciplina associada.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Obter todas as ofertas de turmas desta disciplina na matriz.
     * (Relacionamento para o futuro motor de alocação)
     */
    public function classOfferings()
    {
        return $this->hasMany(ClassOffering::class, 'curriculum_matrix_discipline_id');
    }

    /**
     * Validação: verificar se há conflito de período e disciplina.
     */
    public static function validateUniquePeriodDiscipline(
        int $curriculumMatrixId,
        int $subjectId,
        int $period,
        ?int $ignoreId = null
    ): bool {
        $query = self::where('curriculum_matrix_id', $curriculumMatrixId)
            ->where('subject_id', $subjectId)
            ->where('period', $period);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->doesntExist();
    }

    /**
     * Obter todas as disciplinas de um período em uma matriz.
     */
    public static function getByMatrixAndPeriod(int $curriculumMatrixId, int $period)
    {
        return self::where('curriculum_matrix_id', $curriculumMatrixId)
            ->where('period', $period)
            ->with('subject')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Calcular carga horária total do período.
     */
    public static function totalHoursForPeriod(int $curriculumMatrixId, int $period): int
    {
        return self::where('curriculum_matrix_id', $curriculumMatrixId)
            ->where('period', $period)
            ->sum('weekly_hours');
    }

    /**
     * Obter resumo da matriz.
     */
    public static function getSummaryByMatrix(int $curriculumMatrixId): array
    {
        $disciplines = self::where('curriculum_matrix_id', $curriculumMatrixId)
            ->with('subject')
            ->get();

        $summary = [
            'total_disciplines' => $disciplines->count(),
            'total_hours' => $disciplines->sum('weekly_hours'),
            'by_period' => [],
            'by_offering_type' => [],
            'by_shift' => [],
        ];

        foreach ($disciplines as $discipline) {
            $period = $discipline->period;
            if (!isset($summary['by_period'][$period])) {
                $summary['by_period'][$period] = [
                    'disciplines' => 0,
                    'hours' => 0,
                ];
            }
            $summary['by_period'][$period]['disciplines']++;
            $summary['by_period'][$period]['hours'] += $discipline->weekly_hours;

            $type = $discipline->offering_type;
            if (!isset($summary['by_offering_type'][$type])) {
                $summary['by_offering_type'][$type] = 0;
            }
            $summary['by_offering_type'][$type]++;

            $shift = $discipline->shift;
            if (!isset($summary['by_shift'][$shift])) {
                $summary['by_shift'][$shift] = 0;
            }
            $summary['by_shift'][$shift]++;
        }

        ksort($summary['by_period']);

        return $summary;
    }
}
