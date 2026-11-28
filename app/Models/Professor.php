<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Professor extends Model
{
    protected $fillable = ['registration', 'name', 'email', 'qualification', 'active', 'chapa', 'lattes', 'max_classes_per_day', 'max_days_per_week'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function availabilities()
    {
        return $this->hasMany(ProfessorAvailability::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_professor');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'professor_subject');
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
