<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfessorAvailabilityController extends Controller
{
    public function show(Professor $professor)
    {
        $this->checkAccess();

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

        return view('admin.professors-availability', [
            'professor' => $professor,
            'term' => $term,
            'availabilities' => $availabilities,
            'timeSlots' => $timeSlots,
            'preferredCount' => $preferredCount,
            'availableCount' => $availableCount,
            'unavailableCount' => $unavailableCount,
            'totalSlots' => $totalSlots,
        ]);
    }

    public function showSubjects(Professor $professor)
    {
        $this->checkAccess();

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
        $this->checkAccess();

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
            ['time' => '07:30', 'start' => '07:30', 'end' => '08:20'],
            ['time' => '08:20', 'start' => '08:20', 'end' => '09:10'],
            ['time' => '09:20', 'start' => '09:20', 'end' => '10:10'],
            ['time' => '10:10', 'start' => '10:10', 'end' => '11:00'],
            ['time' => '10:50', 'start' => '10:50', 'end' => '11:40'],
            ['time' => '11:40', 'start' => '11:40', 'end' => '12:30'],
            ['time' => '13:00', 'start' => '13:00', 'end' => '13:50'],
            ['time' => '13:50', 'start' => '13:50', 'end' => '14:40'],
            ['time' => '14:50', 'start' => '14:50', 'end' => '15:40'],
            ['time' => '15:40', 'start' => '15:40', 'end' => '16:30'],
            ['time' => '16:30', 'start' => '16:30', 'end' => '17:20'],
            ['time' => '17:30', 'start' => '17:30', 'end' => '18:20'],
            ['time' => '18:20', 'start' => '18:20', 'end' => '19:10'],
            ['time' => '19:10', 'start' => '19:10', 'end' => '20:00'],
            ['time' => '20:00', 'start' => '20:00', 'end' => '20:50'],
            ['time' => '21:00', 'start' => '21:00', 'end' => '21:50'],
            ['time' => '21:50', 'start' => '21:50', 'end' => '22:40'],
        ];
    }

    private function checkAccess()
    {
        if (! Auth::check() || ! in_array(Auth::user()->role, ['coordinator', 'staff', 'admin'], true)) {
            abort(403, 'Acesso negado. Você não tem permissão para visualizar disponibilidades.');
        }
    }
}
