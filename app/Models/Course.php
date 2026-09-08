<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['code', 'name', 'degree', 'active'];
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
    public function matrices()
    {
        return $this->hasMany(CurriculumMatrix::class);
    }
    public function professors()
    {
        return $this->belongsToMany(Professor::class, 'course_professor');
    }
}
