<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nota prevista de um indicador da auditoria (1–5; nos itens SIM/NÃO, SIM = 5 e NÃO = 1). */
class CourseAuditEntry extends Model
{
    protected $fillable = ['course_audit_id', 'indicator', 'score', 'not_applicable', 'notes', 'updated_by'];

    protected function casts(): array
    {
        return ['not_applicable' => 'boolean', 'score' => 'integer'];
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
