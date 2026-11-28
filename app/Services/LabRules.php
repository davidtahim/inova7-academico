<?php

namespace App\Services;

use App\Models\ClassOffering;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\LabReservation;
use App\Models\Room;
use App\Models\Subject;
use Illuminate\Support\Collection;
use App\Support\LabAreas;
use Illuminate\Support\Str;

/**
 * Regras de uso dos laboratórios de informática (Regulamento PED-RGU-18307).
 *
 * Art. 8º III: laboratório só para disciplinas com carga prática. A disciplina exige
 * laboratório quando a carga prática é > 0 ou quando a matriz pede LABORATORIO_INFORMATICA.
 */
class LabRules
{
    public const LAB = 'LABORATORIO_INFORMATICA';

    /** Horário de funcionamento (Art. 4º): seg–sex 07:30–22:20, sábado 07:30–12:00. */
    public const OPENING = [1 => ['07:30', '22:20'], 2 => ['07:30', '22:20'], 3 => ['07:30', '22:20'], 4 => ['07:30', '22:20'], 5 => ['07:30', '22:20'], 6 => ['07:30', '12:00']];

    /**
     * Tipo de laboratório que a disciplina exige: o pedido na matriz (TI, Saúde, Comunicação,
     * Engenharias, Arquitetura); sem tipo na matriz, carga prática > 0 exige laboratório de
     * informática (Art. 8º III). null = disciplina teórica, sem laboratório.
     */
    public static function requiredLabType(?Subject $subject, ?CurriculumMatrixDiscipline $link = null): ?string
    {
        if (LabAreas::isLabType($link?->required_room_type)) {
            return $link->required_room_type;
        }

        return (int) ($subject?->practice_hours ?? 0) > 0 && ! $link?->required_room_type ? self::LAB : null;
    }

    public static function requiresLab(?Subject $subject, ?CurriculumMatrixDiscipline $link = null): bool
    {
        return self::requiredLabType($subject, $link) !== null;
    }

    public static function offeringRequiresLab(ClassOffering $offering): bool
    {
        return self::requiresLab($offering->subject, $offering->matrixDiscipline);
    }

    /** "Python, VS Code; MySQL" → ['python', 'vs code', 'mysql'] */
    public static function softwareList(?string $text): array
    {
        return collect(preg_split('/[\n,;]+/', (string) $text))
            ->map(fn ($s) => Str::lower(Str::ascii(trim($s))))->filter()->unique()->values()->all();
    }

    /** Softwares exigidos pela disciplina que o laboratório não tem. */
    public static function missingSoftware(?Subject $subject, Room $room): array
    {
        $installed = $room->softwareList();

        return array_values(array_filter(self::softwareList($subject?->required_software),
            fn ($need) => ! collect($installed)->contains(fn ($have) => str_contains($have, $need) || str_contains($need, $have))));
    }

    /**
     * Problemas de usar esta sala para esta turma (vazio = pode).
     *
     * @return array<int, string>
     */
    public static function roomErrors(ClassOffering $offering, Room $room, ?int $students = null): array
    {
        $errors = [];
        $need = self::requiredLabType($offering->subject, $offering->matrixDiscipline);
        $students ??= $offering->student_count;
        $name = $offering->subject?->name;

        if ($need && $room->type !== $need) {
            $errors[] = "{$name} precisa de " . Str::lower(Room::TYPES[$need] ?? $need)
                . ($need === self::LAB ? ' (carga prática, Art. 8º III)' : '') . "; {$room->code} é " . Str::lower($room->type_label) . '.';
        }
        if (! $need && $room->isLab()) {
            $errors[] = $room->isComputerLab()
                ? "{$name} é 100% teórica: laboratório de informática não é usado na grade regular (Art. 8º III). Escolha uma sala comum."
                : "{$name} não exige " . Str::lower($room->type_label) . ": o laboratório fica para as disciplinas práticas da área. Escolha uma sala comum.";
        }
        if ($room->isLab()) {
            if (! $room->active) {
                $errors[] = "Laboratório {$room->code} está inativo.";
            }
            if ($students && $room->computers !== null && $room->computers < $students) {
                $errors[] = "Laboratório {$room->code} tem {$room->computers} " . ($room->isComputerLab() ? 'computadores' : 'vagas') . " para {$students} alunos.";
            }
            if ($room->isComputerLab() && $missing = self::missingSoftware($offering->subject, $room)) {
                $errors[] = "Laboratório {$room->code} não tem os softwares exigidos: " . implode(', ', $missing) . '.';
            }
        }

        return $errors;
    }

    /**
     * Salas que a turma pode usar: prática → laboratórios ativos aptos; teórica → sem laboratórios de informática.
     *
     * @param  Collection<int, Room>|null  $rooms
     * @return Collection<int, Room>
     */
    public static function eligibleRooms(ClassOffering $offering, ?Collection $rooms = null): Collection
    {
        $rooms ??= Room::where('active', true)->orderBy('code')->get();
        $required = $offering->matrixDiscipline?->required_room_type;

        return $rooms->filter(function (Room $room) use ($offering, $required) {
            if (self::roomErrors($offering, $room) !== []) {
                return false;
            }

            // Tipos exigidos que não são laboratório (ex.: sala de prática) continuam valendo.
            return ! $required || LabAreas::isLabType($required) || $room->type === $required;
        })->values();
    }

    /**
     * Reservas aprovadas (avulsas, eventos, provas, monitorias) que chocam com aulas semanais
     * da turma neste laboratório, dentro do semestre.
     *
     * @param  array<int, array{weekday: int, start: string, end: string}>  $intervals
     * @return array<int, string>
     */
    public static function reservationConflicts(ClassOffering $offering, Room $room, array $intervals): array
    {
        $term = $offering->term;
        $from = $term?->starts_at;
        $to = $term?->ends_at;
        $manual = LabReservation::approved()->where('laboratorio_id', $room->id)->where('origem', 'manual')
            ->where(fn ($q) => $q->whereNull('turma_id')->orWhere('turma_id', '!=', $offering->id))->get();

        $conflicts = [];
        foreach ($manual as $reservation) {
            foreach ($intervals as $interval) {
                if (! ($reservation->start() < $interval['end'] && $interval['start'] < $reservation->end())) {
                    continue;
                }
                if ($reservation->recorrente) {
                    if ((int) $reservation->dia_semana === (int) $interval['weekday']) {
                        $conflicts[] = "Laboratório {$room->code} reservado ({$reservation->tipo_solicitacao}: " . ($reservation->titulo ?: 'sem título') . ') toda ' . TimeBlocks::SHORT_WEEKDAYS[$interval['weekday']] . " {$reservation->start()}–{$reservation->end()}.";
                    }
                } elseif ($reservation->data && (int) $reservation->data->dayOfWeekIso === (int) $interval['weekday']
                    && (! $from || $reservation->data->gte($from)) && (! $to || $reservation->data->lte($to))) {
                    $conflicts[] = "Laboratório {$room->code} reservado em {$reservation->data->format('d/m/Y')} {$reservation->start()}–{$reservation->end()} ({$reservation->tipo_solicitacao}: " . ($reservation->titulo ?: 'sem título') . ').';
                }
            }
        }

        return array_values(array_unique($conflicts));
    }
}
