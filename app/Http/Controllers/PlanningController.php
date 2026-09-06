<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Services\ConflictDetector;
use Illuminate\Http\Request;

class PlanningController extends Controller
{
    public function index(Request $request, ConflictDetector $detector)
    {
        $term = AcademicTerm::find($request->integer('term')) ?? AcademicTerm::latest('id')->first();
        $query = ClassOffering::with(['course','subject','assignments.professor','slots.professor'])->when($term, fn ($q) => $q->where('academic_term_id', $term->id));
        if ($request->filled('course')) $query->where('course_id', $request->integer('course'));
        if ($request->filled('shift')) $query->where('shift', $request->string('shift'));
        return view('planning.index', [
            'term' => $term, 'terms' => AcademicTerm::latest('id')->get(), 'courses' => Course::orderBy('name')->get(),
            'offerings' => $query->orderBy('class_code')->get(), 'conflicts' => $term ? $detector->forTerm($term->id) : collect(),
        ]);
    }
}
