<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'registration_number',
        'role',
        'photo_path',
        'is_active',
        'must_change_password',
        'professor_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // Hash automático ao salvar (Laravel 11)
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'professor_id' => 'integer',
    ];

    public static function roleOptions(): array
    {
        return [
            'student' => 'Aluno',
            'teacher' => 'Professor',
            'coordinator' => 'Coordenador',
            'staff' => 'Funcionário (Secretaria/CRA)',
            'admin' => 'Administrador de TI',
        ];
    }

    public function getRoleLabelAttribute(): string
    {
        return self::roleOptions()[$this->role] ?? ucfirst(str_replace('_', ' ', $this->role));
    }

    // Métodos de verificação de permissão (RBAC)
    public function isAdminTi(): bool
    {
        return $this->role === 'admin';
    }

    public function isCoordinator(): bool
    {
        return $this->role === 'coordinator';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function hasRole($role): bool
    {
        if (is_array($role)) {
            return in_array($this->role, $role, true);
        }
        return $this->role === $role;
    }

    public function hasAnyRole($roles): bool
    {
        return $this->hasRole($roles);
    }

    public function canManageUsers(): bool
    {
        return $this->isAdminTi();
    }

    public function canAccessCatalog(): bool
    {
        return in_array($this->role, ['admin', 'coordinator', 'staff'], true);
    }

    public function canAccessPlanning(): bool
    {
        return in_array($this->role, ['admin', 'coordinator'], true);
    }

    public function canAccessAudit(): bool
    {
        return $this->isAdminTi();
    }

    public function canAccessImports(): bool
    {
        return in_array($this->role, ['admin', 'coordinator'], true);
    }

    // Relacionamentos
    public function studentAcademicRecords()
    {
        return $this->hasMany(StudentAcademicRecord::class);
    }

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }
}
