<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Notação de horário da planilha de oferta: cada turno tem aulas de 50 min
 * identificadas por letras (A = 1ª aula do turno). "AB" = 1º bloco de 2 aulas,
 * "CD" = 2º bloco, "AB, CD" = noite inteira.
 *
 *   MANHÃ  A 07:30 · B 08:20 · C 09:10 · D 10:10 · E 11:00 · F 11:50
 *   TARDE  A 13:10 · B 14:00 · C 14:50 · D 15:50 · E 16:40
 *   NOITE  A 18:30 · B 19:20 · C 20:20 · D 21:10
 */
class TimeBlocks
{
    public const WEEKDAYS = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];

    public const SHORT_WEEKDAYS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];

    /** @return array<string, array{start: string, end: string}> letra => horário */
    public static function forShift(string $shift): array
    {
        $ranges = match (Str::upper(Str::ascii($shift))) {
            'MANHA' => [0, 6],
            'TARDE' => [6, 5],
            'NOITE' => [11, 4],
            default => [0, 0],
        };

        $slots = array_slice(AvailabilityGrid::TIME_SLOTS, $ranges[0], $ranges[1]);
        $letters = [];
        foreach (array_values($slots) as $i => $slot) {
            $letters[chr(65 + $i)] = ['start' => $slot['start'], 'end' => $slot['end']];
        }

        return $letters;
    }

    /** "AB, CD" / "abcd" → ['A','B','C','D'] (somente letras válidas para o turno, em ordem). */
    public static function parseLetters(string $value, string $shift): array
    {
        $valid = array_keys(self::forShift($shift));
        $letters = array_unique(str_split(preg_replace('/[^A-Z]/', '', Str::upper($value)) ?: ''));

        return array_values(array_filter($valid, fn ($letter) => in_array($letter, $letters, true)));
    }

    /** "segunda-feira, quarta-feira" / "Quarta" / "seg" → [1, 3] */
    public static function parseWeekdays(string $value): array
    {
        $days = [];
        foreach (preg_split('/\s*(?:,|;|\/|\se\s)\s*/u', Str::lower(Str::ascii($value))) ?: [] as $part) {
            $part = trim($part);
            $day = match (true) {
                str_starts_with($part, 'seg') => 1,
                str_starts_with($part, 'ter') => 2,
                str_starts_with($part, 'qua') => 3,
                str_starts_with($part, 'qui') => 4,
                str_starts_with($part, 'sex') => 5,
                str_starts_with($part, 'sab') => 6,
                default => null,
            };
            if ($day) {
                $days[] = $day;
            }
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }

    /**
     * Intervalos contínuos para gravar em schedule_slots: letras consecutivas viram um só intervalo
     * ("AB" → 18:30–20:10), letras separadas por intervalo/pulo viram intervalos distintos.
     *
     * @return array<int, array{start: string, end: string, letters: string}>
     */
    public static function intervals(array $letters, string $shift): array
    {
        $map = self::forShift($shift);
        $intervals = [];
        $current = null;

        foreach (array_keys($map) as $letter) {
            if (! in_array($letter, $letters, true)) {
                $current = null;
                continue;
            }
            if ($current !== null && $intervals[$current]['end'] === $map[$letter]['start']) {
                // Aulas encostadas (A 18:30–19:20 + B 19:20–20:10) formam um único intervalo.
                $intervals[$current]['end'] = $map[$letter]['end'];
                $intervals[$current]['letters'] .= $letter;
            } else {
                // Intervalo do turno (ex.: 20:10–20:20 entre B e C) separa os blocos AB e CD.
                $intervals[] = ['start' => $map[$letter]['start'], 'end' => $map[$letter]['end'], 'letters' => $letter];
                $current = array_key_last($intervals);
            }
        }

        return $intervals;
    }

    /** Letras de um horário salvo (ex.: 18:30–20:10 na NOITE → "AB"). */
    public static function lettersFor(string $start, string $end, string $shift): string
    {
        $letters = '';
        foreach (self::forShift($shift) as $letter => $slot) {
            if ($slot['start'] >= substr($start, 0, 5) && $slot['end'] <= substr($end, 0, 5)) {
                $letters .= $letter;
            }
        }

        return $letters;
    }

    public static function label(array $letters): string
    {
        return implode('', $letters);
    }
}
