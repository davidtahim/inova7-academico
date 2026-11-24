<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['code', 'name', 'syllabus', 'total_hours', 'presential_hours', 'online_hours', 'practice_hours', 'extension_hours'];

    public function professors()
    {
        return $this->belongsToMany(Professor::class, 'professor_subject');
    }

    public function curriculumMatrices()
    {
        return $this->belongsToMany(CurriculumMatrix::class, 'curriculum_subject')->withPivot(['period', 'sequence']);
    }

    /**
     * Obter os vínculos de disciplina para oferta semestral.
     * Relaciona esta disciplina com cada matriz e período específico.
     */
    public function offeringLinks()
    {
        return $this->hasMany(CurriculumMatrixDiscipline::class)->with('curriculumMatrix');
    }

    /**
     * Obter resumo de quantas vezes esta disciplina está vinculada em diferentes matrizes/períodos.
     */
    public function getOfferingCount()
    {
        return $this->offeringLinks()->count();
    }
}
