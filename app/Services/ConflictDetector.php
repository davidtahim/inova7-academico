<?php

namespace App\Services;

use App\Models\ScheduleSlot;
use Illuminate\Support\Collection;

class ConflictDetector
{
    /** @return Collection<int, array<string, mixed>> */
    public function forTerm(int $termId): Collection
    {
        $slots = ScheduleSlot::query()
            ->with(['offering.subject', 'professor'])
            ->whereHas('offering', fn ($query) => $query->where('academic_term_id', $termId)->where('occurs', true))
            ->get();

        $conflicts = collect();
        foreach ($slots as $index => $left) {
            foreach ($slots->slice($index + 1) as $right) {
                if ($left->weekday !== $right->weekday || !$this->overlaps($left, $right)) continue;

                if ($left->professor_id && $left->professor_id === $right->professor_id) {
                    $conflicts->push($this->record('professor', $left, $right, 'Professor em duas ofertas no mesmo horário.'));
                }
                if ($left->room && mb_strtolower($left->room) === mb_strtolower((string) $right->room)) {
                    $conflicts->push($this->record('room', $left, $right, 'Sala utilizada por duas ofertas no mesmo horário.'));
                }
                if ($left->offering->class_code === $right->offering->class_code) {
                    $conflicts->push($this->record('class', $left, $right, 'Turma com duas disciplinas no mesmo horário.'));
                }
            }
        }

        return $conflicts->unique(fn ($item) => implode('|', [$item['type'], $item['left_id'], $item['right_id']]))->values();
    }

    private function overlaps(ScheduleSlot $left, ScheduleSlot $right): bool
    {
        return $left->starts_at < $right->ends_at && $right->starts_at < $left->ends_at;
    }

    private function record(string $type, ScheduleSlot $left, ScheduleSlot $right, string $message): array
    {
        return [
            'type' => $type, 'message' => $message, 'left_id' => $left->id, 'right_id' => $right->id,
            'professor' => $left->professor?->name, 'day' => $left->weekday,
            'left' => $left->offering->subject->name, 'right' => $right->offering->subject->name,
        ];
    }
}
