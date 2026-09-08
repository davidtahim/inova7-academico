<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurriculumMatrix extends Model
{
    protected $fillable = ['course_id', 'code', 'name', 'version', 'status', 'effective_from'];
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'curriculum_subject')->withPivot(['period', 'sequence'])->orderByPivot('period')->orderByPivot('sequence');
    }
}
