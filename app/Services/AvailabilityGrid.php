<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;

/**
 * Dados da grade de disponibilidade docente (CCG-FOR-02): 15 horários oficiais × Seg–Sáb.
 * Usado pela tela da coordenação e pelo perfil do professor.
 */
class AvailabilityGrid
{
    /** Horários oficiais de 50 min (Manhã 6, Tarde 5, Noite 4). */
    public const TIME_SLOTS = [
        ['time' => '07:30 às 08:20', 'start' => '07:30', 'end' => '08:20'],
        ['time' => '08:20 às 09:10', 'start' => '08:20', 'end' => '09:10'],
        ['time' => '09:10 às 10:00', 'start' => '09:10', 'end' => '10:00'],
        ['time' => '10:10 às 11:00', 'start' => '10:10', 'end' => '11:00'],
        ['time' => '11:00 às 11:50', 'start' => '11:00', 'end' => '11:50'],
        ['time' => '11:50 às 12:40', 'start' => '11:50', 'end' => '12:40'],
        ['time' => '13:10 às 14:00', 'start' => '13:10', 'end' => '14:00'],
        ['time' => '14:00 às 14:50', 'start' => '14:00', 'end' => '14:50'],
        ['time' => '14:50 às 15:40', 'start' => '14:50', 'end' => '15:40'],
        ['time' => '15:50 às 16:40', 'start' => '15:50', 'end' => '16:40'],
        ['time' => '16:40 às 17:30', 'start' => '16:40', 'end' => '17:30'],
        ['time' => '18:30 às 19:20', 'start' => '18:30', 'end' => '19:20'],
        ['time' => '19:20 às 20:10', 'start' => '19:20', 'end' => '20:10'],
        ['time' => '20:20 às 21:10', 'start' => '20:20', 'end' => '21:10'],
        ['time' => '21:10 às 22:00', 'start' => '21:10', 'end' => '22:00'],
    ];

    public static function selectedTerm(): ?AcademicTerm
    {
        return AcademicTerm::current();
    }

    /**
     * Variáveis esperadas pelo componente partials/availability-grid.
     */
    public function build(Professor $professor, ?AcademicTerm $term): array
    {
        $availabilities = $term
            ? ProfessorAvailability::where('professor_id', $professor->id)
                ->where('academic_term_id', $term->id)
                ->orderBy('weekday')
                ->orderBy('starts_at')
                ->get()
            : collect();

        // [weekday]["HH:MM - HH:MM"] = preference
        $saved = [];
        foreach ($availabilities as $item) {
            $key = substr((string) $item->starts_at, 0, 5) . ' - ' . substr((string) $item->ends_at, 0, 5);
            $saved[$item->weekday][$key] = $item->preference;
        }

        $preferred = $availabilities->where('preference', 'preferred')->count();
        $available = $availabilities->where('preference', 'available')->count();
        $total = count(self::TIME_SLOTS) * 6;

        // Outro semestre em que o professor já preencheu a grade (para oferecer cópia).
        $otherTerm = null;
        if ($term && $availabilities->isEmpty()) {
            $otherTerm = AcademicTerm::query()
                ->whereKeyNot($term->id)
                ->whereIn('id', ProfessorAvailability::where('professor_id', $professor->id)->select('academic_term_id'))
                ->orderByDesc('code')->first();
        }

        return [
            'term' => $term,
            'copyFromTerm' => $otherTerm,
            'availabilities' => $availabilities,
            'savedAvailabilities' => $saved,
            'timeSlots' => self::TIME_SLOTS,
            'preferredCount' => $preferred,
            'availableCount' => $available,
            'unavailableCount' => $total - $preferred - $available,
            'totalSlots' => $total,
        ];
    }
}
