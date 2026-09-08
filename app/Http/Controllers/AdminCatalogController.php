<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\Professor;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminCatalogController extends Controller
{
    private function ensureAdmin(): void
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Apenas administradores podem gerenciar o catálogo completo.');
        }
    }

    public function planejamentoIndex()
    {
        $this->ensureAdmin();

        return view('admin.planejamento.index', [
            'coursesCount' => Course::count(),
            'subjectsCount' => Subject::count(),
            'professorsCount' => Professor::count(),
            'termsCount' => AcademicTerm::count(),
        ]);
    }

    public function coursesIndex()
    {
        $this->ensureAdmin();

        return view('admin.courses.index', [
            'courses' => Course::orderBy('name')->get(),
        ]);
    }

    public function courseCreate()
    {
        $this->ensureAdmin();

        return view('admin.courses.form', [
            'course' => new Course(),
        ]);
    }

    public function courseStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:courses,code'],
            'name' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        Course::create($validated);

        return redirect()->route('admin.courses.index')->with('success', 'Curso cadastrado com sucesso!');
    }

    public function courseEdit(Course $course)
    {
        $this->ensureAdmin();

        return view('admin.courses.form', [
            'course' => $course,
        ]);
    }

    public function courseUpdate(Request $request, Course $course)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:courses,code,' . $course->id],
            'name' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        $course->update($validated);

        return redirect()->route('admin.courses.index')->with('success', 'Curso atualizado com sucesso!');
    }

    public function courseDestroy(Course $course)
    {
        $this->ensureAdmin();

        $course->delete();

        return redirect()->route('admin.courses.index')->with('success', 'Curso removido com sucesso!');
    }

    public function matricesIndex()
    {
        $this->ensureAdmin();

        return view('admin.matrices.index', [
            'matrices' => CurriculumMatrix::with('course')->orderBy('name')->get(),
        ]);
    }

    public function importScopesIndex()
    {
        $this->ensureAdmin();

        return view('admin.import-scope.index', [
            'courses' => Course::with('matrices')->orderBy('name')->get(),
        ]);
    }

    public function importScopeEdit(Course $course)
    {
        $this->ensureAdmin();

        $course->load('matrices');

        return view('admin.import-scope.form', [
            'course' => $course,
            'matrices' => $course->matrices()->orderBy('code')->get(),
        ]);
    }

    public function importScopeUpdate(Request $request, Course $course)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'matrix_ids' => ['nullable', 'array'],
            'matrix_ids.*' => ['integer', 'exists:curriculum_matrices,id'],
        ]);

        $selectedIds = collect($validated['matrix_ids'] ?? [])->map(fn($id) => (int) $id)->all();

        $course->matrices()->update(['allowed_for_import' => false]);

        if (! empty($selectedIds)) {
            $course->matrices()->whereIn('id', $selectedIds)->update(['allowed_for_import' => true]);
        }

        return redirect()->route('admin.import-scopes.index')->with('success', 'Matrizes permitidas atualizadas com sucesso!');
    }

    public function matrixCreate()
    {
        $this->ensureAdmin();

        return view('admin.matrices.form', [
            'matrix' => new CurriculumMatrix(),
            'courses' => Course::orderBy('name')->get(),
        ]);
    }

    public function matrixStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:Atual,Ativa,Inativa'],
            'effective_from' => ['nullable', 'date'],
            'allowed_for_import' => ['nullable', 'boolean'],
        ]);

        $validated['allowed_for_import'] = $validated['allowed_for_import'] ?? true;

        CurriculumMatrix::create($validated);

        return redirect()->route('admin.matrices.index')->with('success', 'Matriz cadastrada com sucesso!');
    }

    public function matrixEdit(CurriculumMatrix $matrix)
    {
        $this->ensureAdmin();

        return view('admin.matrices.form', [
            'matrix' => $matrix,
            'courses' => Course::orderBy('name')->get(),
        ]);
    }

    public function matrixUpdate(Request $request, CurriculumMatrix $matrix)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:Atual,Ativa,Inativa'],
            'effective_from' => ['nullable', 'date'],
            'allowed_for_import' => ['nullable', 'boolean'],
        ]);

        $validated['allowed_for_import'] = $validated['allowed_for_import'] ?? $matrix->allowed_for_import ?? true;

        $matrix->update($validated);

        return redirect()->route('admin.matrices.index')->with('success', 'Matriz atualizada com sucesso!');
    }

    public function matrixDestroy(CurriculumMatrix $matrix)
    {
        $this->ensureAdmin();

        $matrix->delete();

        return redirect()->route('admin.matrices.index')->with('success', 'Matriz removida com sucesso!');
    }

    public function termsIndex()
    {
        $this->ensureAdmin();

        return view('admin.semesters.index', [
            'terms' => AcademicTerm::orderBy('code', 'desc')->get(),
        ]);
    }

    public function termCreate()
    {
        $this->ensureAdmin();

        return view('admin.semesters.form', [
            'term' => new AcademicTerm(),
        ]);
    }

    public function termStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:academic_terms,code'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:planning,active,closed'],
        ]);

        AcademicTerm::create($validated);

        return redirect()->route('admin.semestres.index')->with('success', 'Semestre cadastrado com sucesso!');
    }

    public function termEdit(AcademicTerm $term)
    {
        $this->ensureAdmin();

        return view('admin.semesters.form', [
            'term' => $term,
        ]);
    }

    public function termUpdate(Request $request, AcademicTerm $term)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:academic_terms,code,' . $term->id],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:planning,active,closed'],
        ]);

        $term->update($validated);

        return redirect()->route('admin.semestres.index')->with('success', 'Semestre atualizado com sucesso!');
    }

    public function termDestroy(AcademicTerm $term)
    {
        $this->ensureAdmin();

        $term->delete();

        return redirect()->route('admin.semestres.index')->with('success', 'Semestre removido com sucesso!');
    }

    public function subjectsIndex()
    {
        $this->ensureAdmin();

        return view('admin.subjects.index', [
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function subjectCreate()
    {
        $this->ensureAdmin();

        return view('admin.subjects.form', [
            'subject' => new Subject(),
        ]);
    }

    public function subjectStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'syllabus' => ['nullable', 'string'],
            'total_hours' => ['nullable', 'integer', 'min:0'],
            'presential_hours' => ['nullable', 'integer', 'min:0'],
            'online_hours' => ['nullable', 'integer', 'min:0'],
            'practice_hours' => ['nullable', 'integer', 'min:0'],
            'extension_hours' => ['nullable', 'integer', 'min:0'],
        ]);

        Subject::create($validated);

        return redirect()->route('admin.subjects.index')->with('success', 'Disciplina cadastrada com sucesso!');
    }

    public function subjectEdit(Subject $subject)
    {
        $this->ensureAdmin();

        return view('admin.subjects.form', [
            'subject' => $subject,
        ]);
    }

    public function subjectUpdate(Request $request, Subject $subject)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'syllabus' => ['nullable', 'string'],
            'total_hours' => ['nullable', 'integer', 'min:0'],
            'presential_hours' => ['nullable', 'integer', 'min:0'],
            'online_hours' => ['nullable', 'integer', 'min:0'],
            'practice_hours' => ['nullable', 'integer', 'min:0'],
            'extension_hours' => ['nullable', 'integer', 'min:0'],
        ]);

        $subject->update($validated);

        return redirect()->route('admin.subjects.index')->with('success', 'Disciplina atualizada com sucesso!');
    }

    public function subjectDestroy(Subject $subject)
    {
        $this->ensureAdmin();

        $subject->delete();

        return redirect()->route('admin.subjects.index')->with('success', 'Disciplina removida com sucesso!');
    }

    public function professorsIndex()
    {
        $this->ensureAdmin();

        return view('admin.professors.index', [
            'professors' => Professor::orderBy('name')->get(),
        ]);
    }

    public function professorCreate()
    {
        $this->ensureAdmin();

        return view('admin.professors.form', [
            'professor' => new Professor(),
        ]);
    }

    public function professorStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'registration' => ['nullable', 'string', 'max:50', 'unique:professors,registration'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:professors,email'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        Professor::create($validated);

        return redirect()->route('admin.professors.index')->with('success', 'Professor cadastrado com sucesso!');
    }

    public function professorEdit(Professor $professor)
    {
        $this->ensureAdmin();

        return view('admin.professors.form', [
            'professor' => $professor,
        ]);
    }

    public function professorUpdate(Request $request, Professor $professor)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'registration' => ['nullable', 'string', 'max:50', 'unique:professors,registration,' . $professor->id],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:professors,email,' . $professor->id],
            'qualification' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        $professor->update($validated);

        return redirect()->route('admin.professors.index')->with('success', 'Professor atualizado com sucesso!');
    }

    public function professorDestroy(Professor $professor)
    {
        $this->ensureAdmin();

        $professor->delete();

        return redirect()->route('admin.professors.index')->with('success', 'Professor removido com sucesso!');
    }
}
