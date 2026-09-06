<?php

namespace App\Services;

use App\Models\ClassOffering;
use Illuminate\Support\Collection;

class CcgFor01DataBuilder
{
    private array $days = [1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];

    public function build(Collection $offerings, array $header, array $timeSlots): array
    {
        $data = $header;
        foreach ($this->days as $day) foreach ($timeSlots as $slot) $data["{$day}_{$slot['key']}"] = '';

        foreach ($offerings as $offering) {
            foreach ($offering->slots as $slot) {
                $day = $this->days[$slot->weekday] ?? null;
                $matchedTime = collect($timeSlots)->first(fn ($item) => $item['start'] === substr($slot->starts_at, 0, 5));
                $key = $matchedTime['key'] ?? null;
                if (!$day || !$key) continue;
                $data["{$day}_{$key}"] = $this->cell($offering, $slot);
            }
        }
        return $data;
    }

    private function cell(ClassOffering $offering, $slot): string
    {
        if (in_array(mb_strtoupper($offering->modality), ['DOL', 'NAVEGA', 'NOTÁVEL MESTRE', 'NTV MESTRE'])) {
            return '<strong>'.e($offering->subject->name).'</strong><br><span>'.e($offering->modality).'</span>';
        }
        $professor = $slot->professor?->name ?? $offering->assignments->first()?->professor?->name;
        return '<strong>'.e($offering->subject->name).'</strong>'.($professor ? '<br>'.e($professor) : '').($slot->room ? '<br><small>'.e($slot->room).'</small>' : '');
    }
}
