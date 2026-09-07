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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // Hash automático ao salvar (Laravel 11)
        'is_active' => 'boolean',
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
}
