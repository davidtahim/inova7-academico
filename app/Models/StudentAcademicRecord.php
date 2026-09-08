<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAcademicRecord extends Model
{
    protected $fillable = [
        'user_id',
        'academic_term_id',
        'course_id',
        'subject_id',
        'status',
        'grade',
        'credits',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'credits' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function academicTerm()
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
