<?php

namespace App\Policies;

use App\Models\Professor;
use App\Models\User;

/**
 * Regras de acesso aos dados de um professor (disponibilidade CCG-FOR-02 e
 * habilitação em disciplinas).
 *
 * - admin e coordenador: visualizam e editam qualquer professor;
 * - funcionário (secretaria/CRA): apenas visualiza;
 * - professor: visualiza e edita somente o próprio registro;
 * - aluno: sem acesso.
 */
class ProfessorPolicy
{
    public function view(User $user, Professor $professor): bool
    {
        return $user->hasRole(['admin', 'coordinator', 'staff'])
            || $this->isOwnRecord($user, $professor);
    }

    public function updateAvailability(User $user, Professor $professor): bool
    {
        return $user->hasRole(['admin', 'coordinator'])
            || $this->isOwnRecord($user, $professor);
    }

    public function updateSubjects(User $user, Professor $professor): bool
    {
        return $user->hasRole(['admin', 'coordinator'])
            || $this->isOwnRecord($user, $professor);
    }

    private function isOwnRecord(User $user, Professor $professor): bool
    {
        return $user->isTeacher()
            && $user->professor_id !== null
            && (int) $user->professor_id === (int) $professor->getKey();
    }
}
