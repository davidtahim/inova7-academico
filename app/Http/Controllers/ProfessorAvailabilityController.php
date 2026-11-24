<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfessorAvailabilityController extends Controller
{
    public function show(Professor $professor)
    {
        $this->checkAccess($professor);

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

        $preferredCount = $availabilities->where('preference', 'preferred')->count();
        $availableCount = $availabilities->where('preference', 'available')->count();
        $unavailableCount = $availabilities->where('preference', 'unavailable')->count();
        $totalSlots = $preferredCount + $availableCount + $unavailableCount;

        $isEditable = $this->canEditAvailability($professor);

        return view('admin.professors-availability', [
            'professor' => $professor,
            'term' => $term,
            'availabilities' => $availabilities,
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
        $this->checkAccess($professor);
        
        if (!$this->canEditAvailability($professor)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Você não tem permissão para editar essa disponibilidade.'], 403);
            }
            return redirect()->back()->with('error', 'Você não tem permissão para editar essa disponibilidade.');
        }

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

        // Remover todas as disponibilidades do período atual
        ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $term->id)
            ->delete();

        // Adicionar novas disponibilidades
        foreach ($validated['availability_slots'] ?? [] as $slot) {
            ProfessorAvailability::create([
                'professor_id' => $professor->id,
                'academic_term_id' => $term->id,
                'weekday' => $slot['weekday'],
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['ends_at'],
                'preference' => $slot['preference'],
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Disponibilidade atualizada com sucesso!']);
        }

        return redirect()->route('admin.professors.availability', $professor)
            ->with('success', 'Disponibilidade atualizada com sucesso!');
    }

    public function showSubjects(Professor $professor)
    {
        $this->checkAccess($professor);

        $selectedSubjects = $professor->subjects()->orderBy('name')->get();
        $allSubjects = Subject::orderBy('name')->get();

        return view('admin.professors-subjects', [
            'professor' => $professor,
            'selectedSubjects' => $selectedSubjects,
            'allSubjects' => $allSubjects,
            'selectedCount' => $selectedSubjects->count(),
            'totalSubjects' => $allSubjects->count(),
            'unselectedCount' => $allSubjects->count() - $selectedSubjects->count(),
        ]);
    }

    public function updateSubjects(Request $request, Professor $professor)
    {
        $this->checkAccess($professor);

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

    private function checkAccess(Professor $professor)
    {
        if (!Auth::check()) {
            abort(401, 'Você precisa estar autenticado.');
        }

        $user = Auth::user();
        $isAdmin = in_array($user->role, ['coordinator', 'staff', 'admin'], true);
        $isOwnProfessor = $user->role === 'teacher' && $user->professor_id === $professor->id;

        if (!$isAdmin && !$isOwnProfessor) {
            abort(403, 'Acesso negado. Você não tem permissão para visualizar essa disponibilidade.');
        }
    }

    private function canEditAvailability(Professor $professor)
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        $isAdmin = in_array($user->role, ['coordinator', 'staff', 'admin'], true);
        $isOwnProfessor = $user->role === 'teacher' && $user->professor_id === $professor->id;

        return $isAdmin || $isOwnProfessor;
    }
}
