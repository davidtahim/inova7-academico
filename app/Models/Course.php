<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['code', 'name', 'degree', 'coordinator_name', 'coordinator_user_id', 'active'];
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

    public function offerings()
    {
        return $this->hasMany(ClassOffering::class);
    }

    /** Coordenador(a) do curso: usuário da coordenação ou Administrador de TI. */
    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }

    /** Nome impresso no CCG-FOR-02: o informado no curso ou o do usuário coordenador. */
    public function coordinatorDisplayName(): ?string
    {
        return trim((string) $this->coordinator_name) ?: $this->coordinator?->name;
    }

    /** Assistentes do curso: apoiam a coordenação (planejamento, disponibilidade dos professores). */
    public function assistants()
    {
        return $this->belongsToMany(User::class, 'course_assistants')->withTimestamps();
    }
}
