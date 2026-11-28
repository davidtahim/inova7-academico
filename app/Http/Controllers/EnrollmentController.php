<?php

namespace App\Http\Controllers;

use App\Models\CurriculumMatrix;
use App\Models\Subject;
use App\Models\User;
use App\Services\AvailabilityGrid;
use App\Services\EnrollmentPlanner;
use Illuminate\Http\Request;

/**
 * Enturmação dos alunos de matrizes antigas (ex.: SI 2019.1) no semestre selecionado.
 */
class EnrollmentController extends Controller
{
    public function index(Request $request, EnrollmentPlanner $planner)
    {
        $term = AvailabilityGrid::selectedTerm();
        $matrices = CurriculumMatrix::with('course')
            ->whereIn('id', User::where('role', 'student')->whereNotNull('curriculum_matrix_id')->distinct()->pluck('curriculum_matrix_id'))
            ->orderBy('code')->get();
        $matrix = $matrices->firstWhere('id', $request->integer('matrix')) ?? $matrices->first();

        $students = $matrix ? $planner->students($matrix)->load(['subjectStatuses.subject', 'enrollments.offering.subject']) : collect();

        return view('enrollment.index', [
            'term' => $term,
            'matrices' => $matrices,
            'matrix' => $matrix,
            'demand' => $term && $matrix ? $planner->demand($term, $matrix) : collect(),
            'students' => $students,
            'clashes' => $term ? $students->mapWithKeys(fn ($student) => [$student->id => $planner->studentClashes($student, $term)]) : collect(),
        ]);
    }

    public function plan(Request $request, EnrollmentPlanner $planner)
    {
        $term = AvailabilityGrid::selectedTerm();
        $matrix = CurriculumMatrix::findOrFail($request->integer('matrix'));
        abort_unless($term, 404);

        $report = $planner->plan($term, $matrix, $request->boolean('include_in_progress'));

        return redirect()->route('enrollment.index', ['matrix' => $matrix->id])->with('plan_report', $report);
    }

    public function openClass(Request $request, EnrollmentPlanner $planner)
    {
        $term = AvailabilityGrid::selectedTerm();
        $validated = $request->validate([
            'matrix' => ['required', 'integer', 'exists:curriculum_matrices,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'shift' => ['nullable', 'in:MANHÃ,TARDE,NOITE'],
        ]);
        abort_unless($term, 404);

        $matrix = CurriculumMatrix::findOrFail($validated['matrix']);
        $offering = $planner->openDirectedStudyClass($term, $matrix, Subject::findOrFail($validated['subject_id']), $validated['shift'] ?? 'NOITE');

        return redirect()->route('enrollment.index', ['matrix' => $matrix->id])
            ->with('success', "Turma {$offering->class_code} aberta para {$offering->subject->name}. Gere a enturmação novamente para incluir os alunos.");
    }
}
