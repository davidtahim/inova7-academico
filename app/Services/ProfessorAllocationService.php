<?php

namespace App\Services;

use App\Models\ClassOffering;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\ScheduleSlot;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;

class ProfessorAllocationService
{
    public function allocateForTerm(int $termId): int
    {
        $offerings = ClassOffering::query()
            ->with(['slots', 'assignments.professor'])
            ->where('academic_term_id', $termId)
            ->where('occurs', true)
            ->get();

        $allocated = 0;

        foreach ($offerings as $offering) {
            if ($offering->assignments->isNotEmpty()) {
                continue;
            }

            $professor = $this->findBestProfessor($offering);
            if (! $professor) {
                continue;
            }

            foreach ($offering->slots as $slot) {
                ScheduleSlot::query()
                    ->where('class_offering_id', $offering->id)
                    ->where('weekday', $slot->weekday)
                    ->where('starts_at', $slot->starts_at)
                    ->where('ends_at', $slot->ends_at)
                    ->update(['professor_id' => $professor->id]);
            }

            TeachingAssignment::updateOrCreate(
                [
                    'class_offering_id' => $offering->id,
                    'professor_id' => $professor->id,
                ],
                [
                    'weekly_hours' => (float) ($offering->weekly_hours ?? 0),
                    'status' => 'planned',
                ]
            );

            $allocated++;
        }

        return $allocated;
    }

    public function findBestProfessor(ClassOffering $offering): ?Professor
    {
        $slotTimeWindows = $offering->slots->map(fn($slot) => [
            'weekday' => (int) $slot->weekday,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
        ]);

        if ($slotTimeWindows->isEmpty()) {
            return null;
        }

        $professorIds = ProfessorAvailability::query()
            ->where('academic_term_id', $offering->academic_term_id)
            ->whereIn('weekday', $slotTimeWindows->pluck('weekday')->unique()->values())
            ->where(function ($query) use ($slotTimeWindows) {
                foreach ($slotTimeWindows as $slot) {
                    $query->orWhere(function ($subQuery) use ($slot) {
                        $subQuery->where('weekday', $slot['weekday'])
                            ->where('starts_at', '<', $slot['ends_at'])
                            ->where('ends_at', '>', $slot['starts_at']);
                    });
                }
            })
            ->select('professor_id')
            ->distinct()
            ->pluck('professor_id');

        $candidates = Professor::query()
            ->whereIn('id', $professorIds)
            ->get();

        $candidates = $candidates->filter(fn(Professor $professor) => $this->isAvailableForOffering($professor, $offering));

        return $candidates
            ->sortByDesc(fn(Professor $professor) => $this->scoreProfessorAvailability($professor, $offering))
            ->first();
    }

    protected function isAvailableForOffering(Professor $professor, ClassOffering $offering): bool
    {
        foreach ($offering->slots as $slot) {
            $hasConflict = ScheduleSlot::query()
                ->where('professor_id', $professor->id)
                ->where('weekday', $slot->weekday)
                ->where('class_offering_id', '!=', $offering->id)
                ->where('starts_at', '<', $slot->ends_at)
                ->where('ends_at', '>', $slot->starts_at)
                ->exists();

            if ($hasConflict) {
                return false;
            }
        }

        return true;
    }

    protected function scoreProfessorAvailability(Professor $professor, ClassOffering $offering): int
    {
        $score = 0;

        foreach ($offering->slots as $slot) {
            $availability = ProfessorAvailability::query()
                ->where('professor_id', $professor->id)
                ->where('academic_term_id', $offering->academic_term_id)
                ->where('weekday', $slot->weekday)
                ->where('starts_at', '<', $slot->ends_at)
                ->where('ends_at', '>', $slot->starts_at)
                ->first();

            if ($availability) {
                $score += $availability->preference === 'preferred' ? 10 : 5;
            }
        }

        return $score;
    }
}
