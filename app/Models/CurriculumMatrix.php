<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurriculumMatrix extends Model
{
    protected $fillable = ['course_id', 'code', 'name', 'version', 'status', 'effective_from', 'allowed_for_import'];

    protected function casts(): array
    {
        return [
            'allowed_for_import' => 'boolean',
            'effective_from' => 'date',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'curriculum_subject')->withPivot(['period', 'sequence'])->orderByPivot('period')->orderByPivot('sequence');
    }

    /**
     * Obter disciplinas vinculadas com suporte à oferta semestral.
     * Esta é a nova estrutura que suporta carga horária, turno e validações de grade.
     */
    public function disciplinesForOffering()
    {
        return $this->hasMany(CurriculumMatrixDiscipline::class)->with('subject');
    }

    /**
     * Obter todas as disciplinas por período.
     */
    public function getDisciplinesByPeriod(int $period)
    {
        return $this->disciplinesForOffering()
            ->where('period', $period)
            ->orderBy('subject_id')
            ->get();
    }

    /**
     * Obter resumo completo da matriz.
     */
    public function getSummary()
    {
        return CurriculumMatrixDiscipline::getSummaryByMatrix($this->id);
    }
}
