<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Room;
use App\Models\ScheduleSlot;
use Illuminate\Support\Collection;

/**
 * Dados do modal de edição de uma disciplina da turma (professor, horário por dia, sala
 * e carga). Usado na Montagem por turma e nos Horários das turmas; grava sempre em
 * AllocationController::update → ScheduleAllocator::apply.
 */
class AllocationModal
{
    public function __construct(private ScheduleAllocator $allocator) {}

    /** Dados comuns a todas as disciplinas: professores, salas, ocupação e disponibilidade docente. */
    public function context(?AcademicTerm $term): array
    {
        $professors = Professor::where('active', true)->orderBy('name')->get();

        // Onde cada professor já dá aula no semestre ("dia|HH:MM").
        $busy = [];
        if ($term) {
            $slots = ScheduleSlot::with('offering.subject')->whereNotNull('professor_id')
                ->whereHas('offering', fn ($q) => $q->where('academic_term_id', $term->id))->get();
            foreach ($slots as $slot) {
                $o = $slot->offering;
                foreach (str_split(TimeBlocks::lettersFor($slot->starts_at, $slot->ends_at, $o->shift) ?: '') as $letter) {
                    $start = TimeBlocks::forShift($o->shift)[$letter]['start'] ?? null;
                    if ($start) {
                        $busy[$slot->professor_id][$slot->weekday . '|' . $start][] = ['offering' => $o->id, 'label' => ($o->subject?->short_label ?? '') . ' · ' . $o->class_code];
                    }
                }
            }
        }

        $shifts = [];
        foreach (['MANHÃ', 'TARDE', 'NOITE', 'INTEGRAL'] as $shift) {
            $shifts[$shift] = TimeBlocks::forShift($shift);
        }

        return [
            'professors' => $professors,
            'qualified' => Professor::with('subjects:id')->get()->mapWithKeys(fn ($p) => [$p->id => $p->subjects->pluck('id')->all()]),
            'rooms' => Room::where('active', true)->orderBy('code')->get(),
            'busy' => $busy,
            // Grade de disponibilidade (preferred | available | unavailable).
            'availability' => $term ? ProfessorAvailability::where('academic_term_id', $term->id)->get()
                ->groupBy('professor_id')->map(fn ($rows) => $rows->mapWithKeys(fn ($a) => [$a->weekday . '|' . substr((string) $a->starts_at, 0, 5) => $a->preference]))->all() : [],
            'shifts' => $shifts,
            'weekdays' => TimeBlocks::SHORT_WEEKDAYS,
            'directedStudyMax' => ScheduleAllocator::DIRECTED_STUDY_MAX_STUDENTS,
        ];
    }

    /**
     * Dados do modal de cada disciplina das turmas informadas.
     *
     * @param  Collection<int, ClassOffering>  $offerings  com subject, slots, assignments.professor e matrixDiscipline
     * @return array<int, array<string, mixed>>
     */
    public function forOfferings(Collection $offerings, array $context): array
    {
        $feedback = session('allocation');
        $inputs = $offerings->mapWithKeys(fn ($o) => [$o->id => $this->allocator->currentInput($o)]);
        $data = [];

        foreach ($offerings->groupBy('class_code') as $classOfferings) {
            foreach ($classOfferings as $offering) {
                $current = $inputs[$offering->id];
                $mine = ($feedback['id'] ?? null) === $offering->id;
                $failed = $mine && ! ($feedback['saved'] ?? false);
                $link = $offering->matrixDiscipline;
                $placements = $failed ? (array) old('placements', []) : $current['placements'];

                // Ocupação da turma pelas outras disciplinas.
                $turmaBusy = [];
                foreach ($classOfferings as $other) {
                    if ($other->id === $offering->id) {
                        continue;
                    }
                    foreach ($inputs[$other->id]['placements'] as $d => $letters) {
                        foreach ($letters as $l) {
                            $turmaBusy[$d . '|' . $l] = $other->subject->short_label ?? $other->subject->name;
                        }
                    }
                }

                $data[$offering->id] = [
                    'id' => $offering->id,
                    'action' => route('allocation.update', $offering),
                    'name' => $offering->subject->name,
                    'code' => $offering->subject->code,
                    'class' => $offering->class_code,
                    'period' => $offering->period,
                    'shift' => $offering->shift,
                    'expected' => $offering->classesToSchedule(),
                    'practical' => LabRules::offeringRequiresLab($offering),
                    'distance' => $offering->isDistance(),
                    'labelType' => $link?->required_room_type ? (Room::TYPES[$link->required_room_type] ?? $link->required_room_type) : null,
                    'professor' => (int) ($failed ? old('professor_id', $current['professor_id']) : $current['professor_id']) ?: null,
                    'placements' => (object) $placements,
                    'room' => $failed ? old('room', $current['room']) : $current['room'],
                    'students' => $failed ? old('student_count', $offering->student_count) : $offering->student_count,
                    'directed' => (bool) ($failed ? old('directed_study') : $offering->directed_study),
                    'hours' => (float) ($failed ? old('weekly_hours', $offering->weekly_hours) : $offering->weekly_hours),
                    'regular' => (float) ($link?->weekly_hours ?: 2),
                    'qualified' => collect($context['qualified'])->filter(fn ($ids) => in_array($offering->subject_id, $ids, true))->keys()->all(),
                    'rooms' => LabRules::eligibleRooms($offering, $context['rooms'])
                        ->map(fn ($r) => ['code' => $r->code, 'label' => $r->code . ($r->isLab() && $r->computers ? ' · ' . $r->computers . ($r->isComputerLab() ? ' PCs' : ' vagas') : '')])->values(),
                    'turmaBusy' => (object) $turmaBusy,
                    'errors' => $failed ? ($feedback['errors'] ?? []) : [],
                    'warnings' => $mine ? ($feedback['warnings'] ?? []) : [],
                    'open' => $failed,
                ];
            }
        }

        return $data;
    }
}
