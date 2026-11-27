<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProfessorAvailabilityController extends Controller
{
    public function show(Professor $professor)
    {
        $selectedTermId = session('selected_academic_term_id');
        $term = $selectedTermId ? AcademicTerm::find($selectedTermId) : null;
        $term ??= AcademicTerm::latest('id')->first();

        $availabilities = $term && $professor
            ? ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $term->id)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get()
            : collect();

        $timeSlots = $this->generateTimeSlots();

        // Mapear disponibilidades salvas: [weekday][horario] = preference
        $savedAvailabilities = [];
        foreach ($availabilities as $item) {
            // Formatar horário: converter TIME (07:30:00) para formato curto (07:30)
            $startTime = $item->starts_at instanceof \DateTime 
                ? $item->starts_at->format('H:i')
                : substr($item->starts_at, 0, 5); // Se string, pegar primeiros 5 caracteres
            
            $endTime = $item->ends_at instanceof \DateTime 
                ? $item->ends_at->format('H:i')
                : substr($item->ends_at, 0, 5);
            
            $horario = $startTime . ' - ' . $endTime;
            
            if (!isset($savedAvailabilities[$item->weekday])) {
                $savedAvailabilities[$item->weekday] = [];
            }
            $savedAvailabilities[$item->weekday][$horario] = $item->preference;
            
            \Log::info('Disponibilidade carregada', [
                'weekday' => $item->weekday,
                'horario' => $horario,
                'preference' => $item->preference,
                'starts_at_raw' => $item->starts_at,
                'ends_at_raw' => $item->ends_at,
            ]);
        }

        $preferredCount = $availabilities->where('preference', 'preferred')->count();
        $availableCount = $availabilities->where('preference', 'available')->count();
        $totalSlots = count($timeSlots) * 6; // 15 horários × 6 dias = 90
        $unavailableCount = $totalSlots - $preferredCount - $availableCount;

        $isEditable = Gate::allows('updateAvailability', $professor);

        return view('admin.professors-availability', [
            'professor' => $professor,
            'term' => $term,
            'availabilities' => $availabilities,
            'savedAvailabilities' => $savedAvailabilities,
            'timeSlots' => $timeSlots,
            'preferredCount' => $preferredCount,
            'availableCount' => $availableCount,
            'unavailableCount' => $unavailableCount,
            'totalSlots' => $totalSlots,
            'isEditable' => $isEditable,
        ]);
    }

    public function updateAvailability(Request $request, Professor $professor)
    {
        $selectedTermId = session('selected_academic_term_id');
        $term = $selectedTermId ? AcademicTerm::find($selectedTermId) : null;
        $term ??= AcademicTerm::latest('id')->first();

        if (!$term) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Nenhum período acadêmico disponível.'], 400);
            }
            return redirect()->back()->with('error', 'Nenhum período acadêmico disponível.');
        }

        $validated = $request->validate([
            'availability_slots' => ['array'],
            'availability_slots.*.weekday' => ['integer', 'between:1,6'],
            'availability_slots.*.starts_at' => ['date_format:H:i'],
            'availability_slots.*.ends_at' => ['date_format:H:i'],
            'availability_slots.*.preference' => ['string', 'in:preferred,available,unavailable'],
        ]);

        \Log::info('updateAvailability chamado', [
            'professor_id' => $professor->id,
            'term_id' => $term->id,
            'slots_count' => count($validated['availability_slots'] ?? []),
            'slots' => $validated['availability_slots'] ?? []
        ]);

        // Remover todas as disponibilidades do período atual
        ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $term->id)
            ->delete();

        // Adicionar novas disponibilidades
        foreach ($validated['availability_slots'] ?? [] as $slot) {
            $created = ProfessorAvailability::create([
                'professor_id' => $professor->id,
                'academic_term_id' => $term->id,
                'weekday' => $slot['weekday'],
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['ends_at'],
                'preference' => $slot['preference'],
            ]);
            \Log::info('Slot criado', $created->toArray());
        }

        $allSlots = ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $term->id)
            ->get();

        \Log::info('Total de slots após salvar', ['count' => $allSlots->count()]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Disponibilidade atualizada com sucesso!']);
        }

        return redirect()->route('admin.professors.availability', $professor)
            ->with('success', 'Disponibilidade atualizada com sucesso!');
    }

    public function showSubjects(Professor $professor)
    {
        $selectedSubjects = $professor->subjects()->orderBy('name')->get();
        $allSubjects = Subject::orderBy('name')->get();

        return view('admin.professors-subjects', [
            'professor' => $professor,
            'selectedSubjects' => $selectedSubjects,
            'allSubjects' => $allSubjects,
            'selectedCount' => $selectedSubjects->count(),
            'totalSubjects' => $allSubjects->count(),
            'unselectedCount' => $allSubjects->count() - $selectedSubjects->count(),
            'isEditable' => Gate::allows('updateSubjects', $professor),
        ]);
    }

    public function updateSubjects(Request $request, Professor $professor)
    {
        $validated = $request->validate([
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        $subjectIds = collect($validated['subject_ids'] ?? [])->unique()->all();
        $professor->subjects()->sync($subjectIds);

        return redirect()->route('admin.professors.subjects', $professor)
            ->with('success', 'Disciplinas atualizadas com sucesso!');
    }

    private function generateTimeSlots()
    {
        return [
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
    }
}
