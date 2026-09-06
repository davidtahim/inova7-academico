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
        $term = AcademicTerm::where('status', 'planning')->latest('id')->first() ?? AcademicTerm::latest('id')->first();
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
}
