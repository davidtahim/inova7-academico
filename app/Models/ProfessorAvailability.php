<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessorAvailability extends Model
{
    protected $table = 'professor_availabilities';

    protected $fillable = [
        'academic_term_id',
        'professor_id',
        'weekday',
        'starts_at',
        'ends_at',
        'preference',
        'notes',
    ];

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }
}
