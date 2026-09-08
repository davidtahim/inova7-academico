<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\Professor;
use App\Models\ScheduleSlot;
use App\Models\TeachingAssignment;
use App\Services\ConflictDetector;
use App\Services\ProfessorAllocationService;
use Illuminate\Http\Request;

class PlanningController extends Controller
{
    public function index(Request $request, ConflictDetector $detector, ProfessorAllocationService $allocationService)
    {
        $selectedTermId = session('selected_academic_term_id');
        $term = AcademicTerm::find($request->integer('term'))
            ?? AcademicTerm::find($selectedTermId)
            ?? AcademicTerm::latest('id')->first();

        if ($term) {
            session(['selected_academic_term_id' => $term->id]);
        }

        $query = ClassOffering::with(['course', 'subject', 'assignments.professor', 'slots.professor'])->when($term, fn($q) => $q->where('academic_term_id', $term->id));
        if ($request->filled('course')) $query->where('course_id', $request->integer('course'));
        if ($request->filled('shift')) $query->where('shift', $request->string('shift'));

        $offerings = $query
            ->orderByRaw("CASE shift WHEN 'MANHÃ' THEN 1 WHEN 'TARDE' THEN 2 WHEN 'NOITE' THEN 3 ELSE 4 END")
            ->orderBy('subject_id')
            ->orderBy('class_code')
            ->get()->map(function (ClassOffering $offering) use ($allocationService) {
                $suggestedProfessor = $offering->assignments->isEmpty() ? $allocationService->findBestProfessor($offering) : null;
                $offering->suggested_professor = $suggestedProfessor;
                $offering->suggested_professor_name = $suggestedProfessor?->name ?? 'Professor sugerido';
                $offering->compatibility_status = $offering->assignments->isNotEmpty()
                    ? 'Atribuído'
                    : ($suggestedProfessor ? 'Compatível' : 'Pendente');

                return $offering;
            });

        $professors = Professor::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('planning.index', [
            'term' => $term,
            'terms' => AcademicTerm::latest('id')->get(),
            'courses' => Course::orderBy('name')->get(),
            'offerings' => $offerings,
            'professors' => $professors,
            'conflicts' => $term ? $detector->forTerm($term->id) : collect(),
        ]);
    }

    public function confirmProfessor(Request $request, ClassOffering $offering)
    {
        $validated = $request->validate([
            'professor_id' => ['required', 'integer', 'exists:professors,id'],
        ]);

        $user = $request->user();
        if (! $user || ! in_array($user->role, ['coordinator', 'admin', 'staff'], true)) {
            abort(403, 'Você não pode confirmar alocações.');
        }

        $professor = Professor::findOrFail($validated['professor_id']);

        $offering->assignments()->delete();
        $offering->slots()->update(['professor_id' => $professor->id]);

        TeachingAssignment::create([
            'class_offering_id' => $offering->id,
            'professor_id' => $professor->id,
            'weekly_hours' => (float) ($offering->weekly_hours ?? 0),
            'status' => 'confirmed',
        ]);

        $offering->status = 'confirmed';
        $offering->save();

        return redirect()->route('planning.index', ['term' => $offering->academic_term_id])
            ->with('success', 'Professor confirmado para a turma ' . $offering->class_code . '.');
    }

    public function confirmProfessorBulk(Request $request)
    {
        $validated = $request->validate([
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'shift' => ['nullable', 'string'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'professor_id' => ['required', 'integer', 'exists:professors,id'],
        ]);

        $user = $request->user();
        if (! $user || ! in_array($user->role, ['coordinator', 'admin', 'staff'], true)) {
            abort(403, 'Você não pode confirmar alocações em lote.');
        }

        $professor = Professor::findOrFail($validated['professor_id']);

        $query = ClassOffering::query()
            ->where('academic_term_id', $validated['academic_term_id'])
            ->where('occurs', true);

        if (! empty($validated['shift'])) {
            $query->where('shift', $validated['shift']);
        }

        if (! empty($validated['course_id'])) {
            $query->where('course_id', $validated['course_id']);
        }

        $offerings = $query->get();

        foreach ($offerings as $offering) {
            $offering->assignments()->delete();
            $offering->slots()->update(['professor_id' => $professor->id]);

            TeachingAssignment::create([
                'class_offering_id' => $offering->id,
                'professor_id' => $professor->id,
                'weekly_hours' => (float) ($offering->weekly_hours ?? 0),
                'status' => 'confirmed',
            ]);

            $offering->status = 'confirmed';
            $offering->save();
        }

        $label = trim((string) ($validated['shift'] ?? ''));
        $label = $label !== '' ? ' do turno ' . $label : ' do curso selecionado';

        return redirect()->route('planning.index', ['term' => $validated['academic_term_id']])
            ->with('success', 'Professor confirmado em lote' . $label . '.');
    }
}
