<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Auditoria de curso (QUA-INT-08) de um curso num semestre. */
class CourseAudit extends Model
{
    protected $fillable = ['course_id', 'academic_term_id', 'catalog', 'auditor', 'audit_date', 'notes'];

    protected function casts(): array
    {
        return ['audit_date' => 'date'];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function entries()
    {
        return $this->hasMany(CourseAuditEntry::class);
    }

    public function documents()
    {
        return $this->hasMany(CourseAuditDocument::class);
    }
}
