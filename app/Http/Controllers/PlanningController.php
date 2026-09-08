<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
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

        $offerings = $query->orderBy('class_code')->get()->map(function (ClassOffering $offering) use ($allocationService) {
            $suggestedProfessor = $offering->assignments->isEmpty() ? $allocationService->findBestProfessor($offering) : null;
            $offering->suggested_professor = $suggestedProfessor;
            $offering->suggested_professor_name = $suggestedProfessor?->name ?? 'Professor sugerido';
            $offering->compatibility_status = $offering->assignments->isNotEmpty()
                ? 'Atribuído'
                : ($suggestedProfessor ? 'Compatível' : 'Pendente');

            return $offering;
        });

        return view('planning.index', [
            'term' => $term,
            'terms' => AcademicTerm::latest('id')->get(),
            'courses' => Course::orderBy('name')->get(),
            'offerings' => $offerings,
            'conflicts' => $term ? $detector->forTerm($term->id) : collect(),
        ]);
    }
}
