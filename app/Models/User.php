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
        'lab_area',
        'photo_path',
        'is_active',
        'must_change_password',
        'professor_id',
        'curriculum_matrix_id',
        'shift',
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
            'lab_manager' => 'Gestor de laboratórios',
            'admin' => 'Administrador de TI',
        ];
    }

    public function isLabManager(): bool
    {
        return $this->role === 'lab_manager';
    }

    /** Pode administrar (aprovar, cadastrar) os laboratórios desta área? Admin TI: todas. */
    public function managesLabArea(?string $area): bool
    {
        return $this->isAdminTi() || ($this->isLabManager() && $area !== null && $this->lab_area === $area);
    }

    public function getRoleLabelAttribute(): string
    {
        $label = self::roleOptions()[$this->role] ?? ucfirst(str_replace('_', ' ', $this->role));

        return $this->isLabManager() && $this->lab_area ? $label . ' — ' . \App\Support\LabAreas::label($this->lab_area) : $label;
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

    /*
     * Matriz de permissões (fonte única — os Gates em AppServiceProvider delegam para cá).
     *
     *                      admin  coord  staff  teacher  student
     * manage-users           ✔
     * access-catalog         ✔      ✔      ✔
     * manage-catalog         ✔      ✔
     * manage-planning        ✔      ✔
     * access-imports         ✔      ✔      ✔
     * access-audit           ✔      ✔      ✔
     */
    public function canManageUsers(): bool
    {
        return $this->isAdminTi();
    }

    public function canAccessCatalog(): bool
    {
        return $this->hasRole(['admin', 'coordinator', 'staff']);
    }

    public function canManageCatalog(): bool
    {
        return $this->hasRole(['admin', 'coordinator']);
    }

    /**
     * "Entrar como": Administrador de TI e coordenação podem agir pelo professor (disponibilidade,
     * disciplinas) sem a senha dele. Só contas de professor ativas; nunca a própria.
     */
    public function canImpersonate(User $target): bool
    {
        return $this->canManagePlanning() && $target->isTeacher() && $target->is_active && $target->id !== $this->id;
    }

    /** Cursos que este usuário coordena (coordenação ou Administrador de TI que também coordena). */
    public function coordinatedCourses()
    {
        return $this->hasMany(Course::class, 'coordinator_user_id');
    }

    /** Cursos em que este usuário é assistente da coordenação. */
    public function assistedCourses()
    {
        return $this->belongsToMany(Course::class, 'course_assistants')->withTimestamps();
    }

    /** Pode ser assistente de curso: coordenação, Administrador de TI ou funcionário (Secretaria/CRA). */
    public function canAssistCourses(): bool
    {
        return $this->hasRole(['admin', 'coordinator', 'staff']);
    }

    public function canCoordinate(): bool
    {
        return $this->hasRole(['admin', 'coordinator']);
    }

    public function canManagePlanning(): bool
    {
        return $this->hasRole(['admin', 'coordinator']);
    }

    /** @deprecated use canManagePlanning() */
    public function canAccessPlanning(): bool
    {
        return $this->canManagePlanning();
    }

    public function canAccessImports(): bool
    {
        // Importações (oferta Ubíqua, TOTVS, professores): TI, coordenação e secretaria.
        return $this->hasRole(['admin', 'coordinator', 'staff']);
    }

    public function canAccessAudit(): bool
    {
        return $this->hasRole(['admin', 'coordinator', 'staff']);
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

    public function curriculumMatrix()
    {
        return $this->belongsTo(CurriculumMatrix::class);
    }

    public function subjectStatuses()
    {
        return $this->hasMany(StudentSubjectStatus::class);
    }

    public function enrollments()
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    /**
     * Registro docente do usuário professor. Usa o vínculo professor_id e, para contas
     * antigas sem vínculo, procura pelo e-mail (sem pegar o registro de outro usuário)
     * e grava o vínculo encontrado.
     */
    public function resolveProfessor(): ?Professor
    {
        if (! $this->isTeacher()) {
            return null;
        }

        if ($this->professor_id && $this->professor) {
            return $this->professor;
        }

        $professor = Professor::query()
            ->where('email', $this->email)
            ->whereDoesntHave('user', fn ($query) => $query->whereKeyNot($this->getKey()))
            ->first();

        if ($professor) {
            $this->forceFill(['professor_id' => $professor->id])->save();
            $this->setRelation('professor', $professor);
        }

        return $professor;
    }
}
