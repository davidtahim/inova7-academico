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
}
