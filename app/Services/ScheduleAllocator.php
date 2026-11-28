<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Room;
use App\Models\ScheduleSlot;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Motor de alocação da grade semanal (a lógica da planilha de oferta: professor, dia,
 * blocos AB/CD e sala por disciplina da turma), com validação de choques.
 *
 * Entrada de uma alocação:
 *   ['professor_id' => ?int, 'weekdays' => int[], 'letters' => string[], 'room' => ?string]
 */
class ScheduleAllocator
{
    /** Até este número de alunos a turma vira Estudo Dirigido (padrão observado na oferta 2026.1). */
    public const DIRECTED_STUDY_MAX_STUDENTS = 8;

    /**
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    public function validate(ClassOffering $offering, array $input): array
    {
        $errors = [];
        $warnings = [];

        $weekdays = array_values(array_unique(array_map('intval', $input['weekdays'] ?? [])));
        $letters = TimeBlocks::parseLetters(implode('', $input['letters'] ?? []), $offering->shift);
        $professor = ! empty($input['professor_id']) ? Professor::find($input['professor_id']) : null;
        $roomCode = trim((string) ($input['room'] ?? ''));
        $room = $roomCode !== '' ? Room::where('code', $roomCode)->first() : null;

        if (($input['letters'] ?? []) !== [] && $letters === []) {
            $errors[] = "Horário inválido para o turno {$offering->shift}.";
        }
        if ($weekdays === [] xor $letters === []) {
            $errors[] = 'Informe o dia e o horário (blocos) juntos.';
        }
        if ($roomCode !== '' && ! $room) {
            $errors[] = "Sala \"{$roomCode}\" não cadastrada.";
        }

        $intervals = TimeBlocks::intervals($letters, $offering->shift);
        $link = $offering->matrixDiscipline;
        $isDirected = array_key_exists('directed_study', $input) ? (bool) $input['directed_study'] : (bool) $offering->directed_study;

        if ($room && $link?->required_room_type && $room->type !== $link->required_room_type) {
            $errors[] = 'A disciplina exige ' . (Room::TYPES[$link->required_room_type] ?? $link->required_room_type)
                . ", mas {$room->code} é " . Str::lower($room->type_label) . '.';
        }

        if ($weekdays !== [] && $intervals !== []) {
            foreach ($this->overlappingSlots($offering, $weekdays, $intervals) as $slot) {
                $other = $slot->offering;
                $when = TimeBlocks::SHORT_WEEKDAYS[$slot->weekday] . ' ' . substr($slot->starts_at, 0, 5);
                $joint = $professor && (int) $slot->professor_id === (int) $professor->id && $this->sameSubject($offering, $other);

                // Estudo Dirigido: o professor atende vários grupos pequenos ao mesmo tempo, às vezes
                // na mesma sala (prática da oferta 2026.1). Sobreposição entre EDs é só aviso.
                if ($isDirected && $other->directed_study) {
                    $warnings[] = "Estudo dirigido simultâneo com {$other->subject->name} ({$other->class_code}) em {$when}.";
                    continue;
                }

                if ($other->class_code === $offering->class_code && $other->id !== $offering->id) {
                    $errors[] = "A turma {$offering->class_code} já tem {$other->subject->name} em {$when}.";
                }
                if ($joint) {
                    $warnings[] = "Aula conjunta com a turma {$other->class_code} ({$when}), mesma disciplina e professor.";
                    continue;
                }
                if ($professor && (int) $slot->professor_id === (int) $professor->id) {
                    $errors[] = "{$professor->name} já leciona {$other->subject->name} ({$other->class_code}) em {$when}.";
                }
                if ($room && $slot->room && Str::lower($slot->room) === Str::lower($room->code)) {
                    $errors[] = "Sala {$room->code} ocupada por {$other->subject->name} ({$other->class_code}) em {$when}.";
                }
            }

            if ($professor) {
                [$availabilityErrors, $availabilityWarnings] = $this->checkAvailability($professor, $offering, $weekdays, $letters);
                array_push($errors, ...$availabilityErrors);
                array_push($warnings, ...$availabilityWarnings);
            }

            $maxConsecutive = (int) ($link?->max_consecutive_classes ?: 2);
            foreach ($intervals as $interval) {
                if (strlen($interval['letters']) > $maxConsecutive) {
                    $warnings[] = 'Bloco ' . $interval['letters'] . " passa de {$maxConsecutive} aulas seguidas.";
                }
            }

            if (! $room && Str::upper((string) $offering->modality) === 'PRESENCIAL') {
                $warnings[] = 'Turma presencial sem sala definida.';
            }

            // RN01: aulas distribuídas na semana × carga da disciplina.
            $distributed = count($letters) * count($weekdays);
            $expected = $offering->classesToSchedule();
            if ($offering->isDistance()) {
                $warnings[] = "Disciplina a distância ({$offering->modality}): não precisa de horário na grade.";
            } elseif ($expected > 0 && $distributed !== $expected) {
                $warnings[] = "Distribuídas {$distributed} aula(s) na semana; a carga da disciplina é {$expected}.";
            }

            if ($professor) {
                array_push($warnings, ...$this->checkProfessorLimits($professor, $offering, $weekdays, count($letters)));
            }
        }

        if ($professor) {
            if (! $professor->active) {
                $errors[] = "{$professor->name} está inativo.";
            }
            if (! $professor->subjects()->whereKey($offering->subject_id)->exists()) {
                $warnings[] = "{$professor->name} não está habilitado em {$offering->subject->name}.";
            }
        }

        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * Valida e grava professor + horários da disciplina na turma.
     *
     * @return array{saved: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function apply(ClassOffering $offering, array $input, string $assignmentStatus = 'confirmed'): array
    {
        $result = $this->validate($offering, $input);
        if ($result['errors'] !== []) {
            return ['saved' => false] + $result;
        }

        DB::transaction(function () use ($offering, $input, $assignmentStatus) {
            $professorId = ! empty($input['professor_id']) ? (int) $input['professor_id'] : null;
            $letters = TimeBlocks::parseLetters(implode('', $input['letters'] ?? []), $offering->shift);
            $room = trim((string) ($input['room'] ?? '')) ?: null;

            $offering->fill(array_filter([
                'student_count' => array_key_exists('student_count', $input) ? $input['student_count'] : null,
                'directed_study' => array_key_exists('directed_study', $input) ? (bool) $input['directed_study'] : null,
                'weekly_hours' => array_key_exists('weekly_hours', $input) ? $input['weekly_hours'] : null,
            ], fn ($value) => $value !== null))->save();

            TeachingAssignment::where('class_offering_id', $offering->id)
                ->when($professorId, fn ($q) => $q->where('professor_id', '!=', $professorId))
                ->delete();

            if ($professorId) {
                TeachingAssignment::updateOrCreate(
                    ['class_offering_id' => $offering->id, 'professor_id' => $professorId],
                    ['weekly_hours' => $offering->weekly_hours, 'status' => $assignmentStatus]
                );
            }

            ScheduleSlot::where('class_offering_id', $offering->id)->delete();
            foreach (array_unique(array_map('intval', $input['weekdays'] ?? [])) as $weekday) {
                foreach (TimeBlocks::intervals($letters, $offering->shift) as $interval) {
                    ScheduleSlot::create([
                        'class_offering_id' => $offering->id,
                        'professor_id' => $professorId,
                        'weekday' => $weekday,
                        'starts_at' => $interval['start'],
                        'ends_at' => $interval['end'],
                        'room' => $room,
                        'block' => $interval['letters'],
                    ]);
                }
            }
        });

        return ['saved' => true] + $result;
    }

    /**
     * Estudo Dirigido e hora-aula docente sugeridos pela quantidade de alunos.
     *
     * @return array{directed_study: bool, weekly_hours: float}
     */
    public function suggestLoad(ClassOffering $offering, ?int $students): array
    {
        $regular = (float) ($offering->matrixDiscipline?->weekly_hours ?: 2);

        if ($students === null) {
            return ['directed_study' => (bool) $offering->directed_study, 'weekly_hours' => (float) ($offering->weekly_hours ?: $regular)];
        }
        if ($students <= self::DIRECTED_STUDY_MAX_STUDENTS) {
            return ['directed_study' => true, 'weekly_hours' => $students === 0 ? 0.0 : 1.0];
        }

        return ['directed_study' => false, 'weekly_hours' => $regular];
    }

    /** Alocação atual da oferta no formato de entrada (para preencher formulários). */
    public function currentInput(ClassOffering $offering): array
    {
        $slots = $offering->slots;

        return [
            'professor_id' => $offering->assignments->first()?->professor_id,
            'weekdays' => $slots->pluck('weekday')->map(fn ($d) => (int) $d)->unique()->sort()->values()->all(),
            'letters' => str_split($slots->map(fn ($s) => TimeBlocks::lettersFor($s->starts_at, $s->ends_at, $offering->shift))->unique()->implode('') ?: '') ?: [],
            'room' => $slots->first()?->room,
        ];
    }

    /**
     * Monta a grade de um semestre a partir de outro já fechado: para cada disciplina da turma
     * sem horário no destino, procura a mesma disciplina (pelo nome) no mesmo curso e turno da
     * origem e reaproveita professor, dias, blocos e sala — somente se não houver choque.
     *
     * @return array{copied: int, professor_only: int, without_match: int, skipped_distance: int, conflicts: array<int, string>}
     */
    public function copyFromTerm(AcademicTerm $source, AcademicTerm $target): array
    {
        $report = ['copied' => 0, 'professor_only' => 0, 'without_match' => 0, 'skipped_distance' => 0, 'conflicts' => []];

        $sourceOfferings = ClassOffering::with(['subject', 'slots', 'assignments'])
            ->where('academic_term_id', $source->id)
            ->get();

        $targets = ClassOffering::with(['subject', 'slots', 'assignments', 'matrixDiscipline'])
            ->where('academic_term_id', $target->id)
            ->where('occurs', true)
            ->orderBy('course_id')->orderBy('period')->orderBy('class_code')
            ->get()
            ->filter(fn (ClassOffering $o) => $o->slots->isEmpty());

        foreach ($targets as $offering) {
            $match = $this->findEquivalent($offering, $sourceOfferings);
            if (! $match) {
                $report['without_match']++;
                continue;
            }
            if (Str::upper((string) $match->modality) === 'DOL') {
                $report['skipped_distance']++;
                continue;
            }

            $previous = $this->currentInput($match);
            $professorId = $offering->assignments->first()?->professor_id ?? $previous['professor_id'];
            $input = ['professor_id' => $professorId] + $previous;

            $result = $this->apply($offering, $input, 'planned');
            if ($result['saved']) {
                $report['copied']++;
                continue;
            }

            // Horário anterior choca com algo já montado: mantém ao menos o professor sugerido.
            $report['conflicts'][] = "{$offering->class_code} · {$offering->subject->name}: " . $result['errors'][0];
            if ($professorId && $offering->assignments->isEmpty()) {
                $this->apply($offering, ['professor_id' => $professorId], 'planned');
                $report['professor_only']++;
            }
        }

        return $report;
    }

    private function findEquivalent(ClassOffering $offering, Collection $candidates): ?ClassOffering
    {
        $name = $this->normalizedName($offering->subject->name);
        $sameName = $candidates->filter(fn (ClassOffering $c) => $c->subject_id === $offering->subject_id
            || $this->normalizedName($c->subject->name) === $name);

        return $sameName->first(fn ($c) => $c->course_id === $offering->course_id && $c->shift === $offering->shift && (int) $c->period === (int) $offering->period)
            ?? $sameName->first(fn ($c) => $c->course_id === $offering->course_id && $c->shift === $offering->shift)
            ?? $sameName->first(fn ($c) => $c->shift === $offering->shift);
    }

    /** @return Collection<int, ScheduleSlot> horários de outras ofertas do semestre que se sobrepõem */
    private function overlappingSlots(ClassOffering $offering, array $weekdays, array $intervals): Collection
    {
        return ScheduleSlot::query()
            ->with(['offering.subject'])
            ->where('class_offering_id', '!=', $offering->id)
            ->whereIn('weekday', $weekdays)
            ->whereHas('offering', fn ($q) => $q->where('academic_term_id', $offering->academic_term_id)->where('occurs', true))
            ->where(function ($query) use ($intervals) {
                foreach ($intervals as $interval) {
                    $query->orWhere(fn ($q) => $q->where('starts_at', '<', $interval['end'])->where('ends_at', '>', $interval['start']));
                }
            })
            ->get();
    }

    /**
     * RF02: máximo de aulas por dia e de dias de aula na semana (somando o semestre todo).
     *
     * @return array<int, string>
     */
    private function checkProfessorLimits(Professor $professor, ClassOffering $offering, array $weekdays, int $classesPerDay): array
    {
        if (! $professor->max_classes_per_day && ! $professor->max_days_per_week) {
            return [];
        }

        $perDay = [];
        ScheduleSlot::with('offering')
            ->where('professor_id', $professor->id)
            ->where('class_offering_id', '!=', $offering->id)
            ->whereHas('offering', fn ($q) => $q->where('academic_term_id', $offering->academic_term_id))
            ->get()
            ->each(function (ScheduleSlot $slot) use (&$perDay) {
                $perDay[(int) $slot->weekday] = ($perDay[(int) $slot->weekday] ?? 0)
                    + strlen(TimeBlocks::lettersFor($slot->starts_at, $slot->ends_at, $slot->offering->shift));
            });
        foreach ($weekdays as $weekday) {
            $perDay[$weekday] = ($perDay[$weekday] ?? 0) + $classesPerDay;
        }

        $warnings = [];
        if ($professor->max_classes_per_day) {
            foreach ($perDay as $weekday => $count) {
                if ($count > $professor->max_classes_per_day) {
                    $warnings[] = "{$professor->name} ficaria com {$count} aulas na " . Str::lower(TimeBlocks::WEEKDAYS[$weekday])
                        . " (máximo {$professor->max_classes_per_day}).";
                }
            }
        }
        if ($professor->max_days_per_week && count($perDay) > $professor->max_days_per_week) {
            $warnings[] = "{$professor->name} ficaria com aulas em " . count($perDay) . " dias (máximo {$professor->max_days_per_week}).";
        }

        return $warnings;
    }

    /** @return array{0: array<int, string>, 1: array<int, string>} */
    private function checkAvailability(Professor $professor, ClassOffering $offering, array $weekdays, array $letters): array
    {
        $availability = ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $offering->academic_term_id)
            ->get();

        if ($availability->isEmpty()) {
            return [[], ["{$professor->name} ainda não informou disponibilidade para este semestre."]];
        }

        $map = TimeBlocks::forShift($offering->shift);
        $blocked = [];
        foreach ($weekdays as $weekday) {
            foreach ($letters as $letter) {
                $cell = $availability->first(fn ($a) => (int) $a->weekday === $weekday && substr((string) $a->starts_at, 0, 5) === $map[$letter]['start']);
                if (! $cell || $cell->preference === 'unavailable') {
                    $blocked[] = TimeBlocks::SHORT_WEEKDAYS[$weekday] . ' ' . $map[$letter]['start'];
                }
            }
        }

        return $blocked === []
            ? [[], []]
            : [["{$professor->name} está indisponível em: " . implode(', ', array_unique($blocked)) . '.'], []];
    }

    private function sameSubject(ClassOffering $a, ClassOffering $b): bool
    {
        return $a->subject_id === $b->subject_id
            || $this->normalizedName($a->subject->name) === $this->normalizedName($b->subject->name);
    }

    private function normalizedName(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name))) ?? '';
    }
}
