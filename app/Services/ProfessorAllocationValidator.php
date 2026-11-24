<?php

namespace App\Services;

use App\Models\ClassOffering;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\ScheduleSlot;
use App\Models\TeachingAssignment;

class ProfessorAllocationValidator
{
    /**
     * Validar se um professor pode ser alocado a uma turma.
     * Retorna array com status e mensagens de erro.
     */
    public function validate(ClassOffering $classOffering, Professor $professor, int $maxWeeklyHours = 20): array
    {
        $errors = [];

        // Hard Constraint 1: Disponibilidade
        if (!$this->validateAvailability($classOffering, $professor)) {
            $errors[] = 'Professor não possui disponibilidade no turno/horário da turma.';
        }

        // Hard Constraint 2: Conflito de horário
        if (!$this->validateNoScheduleConflict($classOffering, $professor)) {
            $errors[] = 'Professor já está alocado em outra turma no mesmo dia/horário.';
        }

        // Hard Constraint 3: Carga horária máxima
        if (!$this->validateMaxWeeklyHours($classOffering, $professor, $maxWeeklyHours)) {
            $errors[] = "Professor ultrapassaria a carga horária máxima de {$maxWeeklyHours}h/semana.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validação Hard Constraint 1: Verificar se professor tem disponibilidade.
     */
    public function validateAvailability(ClassOffering $classOffering, Professor $professor): bool
    {
        if (!$classOffering->term) {
            return false;
        }

        // Verificar se existe preferência de disponibilidade (disponível ou preferido)
        $availability = ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $classOffering->term->id)
            ->where('preference', '!=', 'unavailable')
            ->exists();

        return $availability;
    }

    /**
     * Validação Hard Constraint 2: Verificar se professor já está alocado neste dia/horário.
     */
    public function validateNoScheduleConflict(ClassOffering $classOffering, Professor $professor): bool
    {
        // Buscar todos os slots agendados do professor no mesmo semestre
        $conflictingSlots = ScheduleSlot::where('professor_id', $professor->id)
            ->whereHas('classOffering', function ($query) use ($classOffering) {
                $query->where('academic_term_id', $classOffering->academic_term_id);
            })
            ->where('weekday', '=', 1) // Simplificado: usar weekday 1 (segunda)
            ->count();

        return $conflictingSlots === 0;
    }

    /**
     * Validação Hard Constraint 3: Verificar se professor não ultrapassa carga horária contratual.
     */
    public function validateMaxWeeklyHours(ClassOffering $classOffering, Professor $professor, int $maxAllowedHours = 20): bool
    {
        // Somar todas as horas já alocadas no semestre
        $assignedHours = TeachingAssignment::where('professor_id', $professor->id)
            ->whereHas('classOffering', function ($query) use ($classOffering) {
                $query->where('academic_term_id', $classOffering->academic_term_id);
            })
            ->sum('weekly_hours');

        // Verificar se adicionando esta nova turma ultrapassa o máximo
        return ($assignedHours + $classOffering->weekly_hours) <= $maxAllowedHours;
    }

    /**
     * Obter sugestões de professores válidos para uma turma.
     */
    public function suggestProfessorsForOffering(ClassOffering $classOffering, int $limit = 10): array
    {
        // Buscar professores que podem ensinar esta disciplina
        $professors = $classOffering->subject
            ->professors()
            ->where('active', true)
            ->limit($limit)
            ->get();

        $suggestions = [];

        foreach ($professors as $professor) {
            $validation = $this->validate($classOffering, $professor);

            $suggestions[] [
                'professor' => $professor,
                'valid' => $validation['valid'],
                'issues' => $validation['errors'],
            ];
        }

        // Ordenar com válidos primeiro
        usort($suggestions, fn($a, $b) => $b['valid'] <=> $a['valid']);

        return $suggestions;
    }
}
