<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleScenario extends Model
{
    public const KINDS = ['manual' => 'Salva', 'auto' => 'Automática', 'import' => 'Importada'];

    protected $fillable = ['academic_term_id', 'name', 'kind', 'payload', 'offerings_count', 'classes_count', 'score', 'hard', 'created_by'];

    protected $casts = ['payload' => 'array'];

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
