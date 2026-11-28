<?php

namespace App\Http\Controllers;

use App\Models\CurriculumMatrix;
use App\Models\Subject;
use App\Models\User;
use App\Services\AvailabilityGrid;
use App\Services\EnrollmentPlanner;
use App\Services\StudentHistoryImporter;
use App\Services\StudentHistoryPdfReader;
use Illuminate\Http\Request;

/**
 * Enturmação dos alunos de matrizes antigas (ex.: SI 2019.1) no semestre selecionado.
 */
class EnrollmentController extends Controller
{
    public function index(Request $request, EnrollmentPlanner $planner)
    {
        $term = AvailabilityGrid::selectedTerm();
        $matrices = \App\Support\CourseScope::filter(CurriculumMatrix::with('course'))
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
            // Para importar históricos: todas as matrizes, com a SI 2019.1 sugerida.
            'importMatrices' => \App\Support\CourseScope::filter(CurriculumMatrix::with('course'))->orderBy('code')->get(),
            'defaultImportMatrix' => $matrix?->id ?? CurriculumMatrix::where('code', StudentHistoryImporter::CURRICULUM_TO_MATRIX['GSI2019.1'])->value('id'),
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

    /**
     * Importa os históricos em PDF (ou o texto do pdftotext) direto, sem CSV.
     * Os arquivos não são guardados: só a situação de cada disciplina.
     */
    public function importHistories(Request $request, StudentHistoryPdfReader $reader, StudentHistoryImporter $importer)
    {
        $validated = $request->validate([
            'matrix' => ['required', 'integer', 'exists:curriculum_matrices,id'],
            'historicos' => ['required', 'array', 'max:300'],
            'historicos.*' => ['file', 'mimes:pdf,txt', 'max:10240'],
        ], [
            'historicos.required' => 'Escolha ao menos um histórico em PDF.',
            'historicos.*.mimes' => 'Envie históricos em PDF.',
        ]);
        $matrix = CurriculumMatrix::findOrFail($validated['matrix']);

        $rows = [];
        $failed = [];
        foreach ($request->file('historicos') as $file) {
            $name = $file->getClientOriginalName();
            try {
                $path = $file->getRealPath();
                $text = strtolower($file->getClientOriginalExtension()) === 'txt' ? file_get_contents($path) : $reader->text($path);
                $histories = $reader->parseAll($text);
            } catch (\Throwable $e) {
                report($e);
                $histories = [];
            }
            $found = collect($histories)->filter(fn ($h) => $h['rows'] !== []);
            if ($found->isEmpty()) {
                $failed[] = $name;
                continue;
            }
            foreach ($found as $history) {
                array_push($rows, ...$history['rows']);
            }
        }

        if ($rows === []) {
            return back()->withErrors(['historicos' => 'Não foi possível ler nenhum histórico: ' . implode(', ', $failed) . '. Confira se são os PDFs do histórico escolar (com texto, não digitalizados).']);
        }

        $result = $importer->import($rows, 'Históricos PDF ' . now()->format('d/m/Y H:i'), $matrix);
        $message = ($result['students'] === 1 ? '1 aluno importado' : "{$result['students']} alunos importados")
            . " para {$matrix->name} ({$result['statuses']} situações de disciplina). Agora gere a enturmação.";

        return redirect()->route('enrollment.index', ['matrix' => $matrix->id])
            ->with('success', $message)
            ->with('import_failed', $failed);
    }
}
