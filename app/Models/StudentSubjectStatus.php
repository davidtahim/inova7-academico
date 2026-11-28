<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSubjectStatus extends Model
{
    public const DONE = ['aprovada', 'dispensada'];

    public const LABELS = [
        'aprovada' => 'Aprovada',
        'dispensada' => 'Dispensada',
        'matriculada' => 'Matriculada (sem resultado)',
        'reprovada' => 'Reprovada',
        'trancada' => 'Trancada/abandono',
        'falta_cursar' => 'Falta cursar',
    ];

    protected $fillable = ['user_id', 'subject_id', 'status', 'term_code', 'matrix_period', 'grade', 'source'];

    protected function casts(): array
    {
        return ['grade' => 'decimal:2', 'matrix_period' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
