<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AuditDocument;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\Professor;
use App\Services\ConflictDetector;

class DashboardController extends Controller
{
    public function __invoke(ConflictDetector $detector)
    {
        $selectedTermId = session('selected_academic_term_id');
        $term = AcademicTerm::find($selectedTermId)
            ?? AcademicTerm::where('status', 'planning')->latest('id')->first()
            ?? AcademicTerm::latest('id')->first();

        if ($term) {
            session(['selected_academic_term_id' => $term->id]);
        }

        $offerings = $term ? ClassOffering::where('academic_term_id', $term->id) : ClassOffering::query()->whereRaw('1=0');
        return view('dashboard.index', [
            'term' => $term,
            'courses' => Course::where('active', true)->count(),
            'professors' => Professor::where('active', true)->count(),
            'offerings' => (clone $offerings)->count(),
            'pending' => (clone $offerings)->where('status', '!=', 'confirmed')->count(),
            'conflicts' => $term ? $detector->forTerm($term->id) : collect(),
            'auditDocuments' => AuditDocument::latest()->limit(5)->get(),
        ]);
    }

    public function selectAcademicTerm(
        \Illuminate\Http\Request $request
    ) {
        $request->validate([
            'academic_term_id' => ['nullable', 'exists:academic_terms,id'],
        ]);

        if ($request->filled('academic_term_id')) {
            session(['selected_academic_term_id' => $request->integer('academic_term_id')]);
        }

        return redirect()->back();
    }
}
