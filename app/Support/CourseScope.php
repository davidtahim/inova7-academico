<?php

namespace App\Support;

use App\Models\CurriculumMatrix;
use App\Models\User;

/**
 * Cursos que o usuário logado pode ver e alterar.
 *
 * Coordenação: só os cursos em que é coordenador(a) ou assistente. Sem nenhum curso vinculado
 * (transição), continua vendo todos, com aviso para o Administrador de TI vincular.
 * Administrador de TI e demais perfis: sem restrição por curso (as permissões vêm do perfil).
 */
class CourseScope
{
    /** null = todos os cursos; array = ids permitidos (pode ser vazio). */
    public static function ids(?User $user = null): ?array
    {
        $user ??= auth()->user();
        if (! $user instanceof User || $user->role !== 'coordinator') {
            return null;
        }

        $ids = $user->coordinatedCourses()->pluck('id')
            ->merge($user->assistedCourses()->pluck('courses.id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();

        return $ids ?: null;
    }

    /** Coordenação ainda sem curso vinculado (vê todos até o vínculo). */
    public static function unlinkedCoordinator(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User && $user->role === 'coordinator' && self::ids($user) === null;
    }

    public static function restricted(?User $user = null): bool
    {
        return self::ids($user) !== null;
    }

    public static function allows(?int $courseId, ?User $user = null): bool
    {
        $ids = self::ids($user);

        return $ids === null || ($courseId !== null && in_array($courseId, $ids, true));
    }

    /** Restringe a consulta aos cursos permitidos (coluna course_id ou id de courses). */
    public static function filter($query, string $column = 'course_id')
    {
        $ids = self::ids();

        return $ids === null ? $query : $query->whereIn($column, $ids ?: [0]);
    }

    public static function allowsMatrix(?int $matrixId): bool
    {
        return ! self::restricted() || self::allows(CurriculumMatrix::whereKey($matrixId)->value('course_id'));
    }
}
