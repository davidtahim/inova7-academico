<?php

namespace App\Http\Controllers;

use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CurriculumMatrixDisciplineController extends Controller
{
    private function ensureAdmin(): void
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Apenas administradores podem gerenciar vínculos de disciplinas.');
        }
    }

    private function ensureCatalogAccess(): void
    {
        if (!Auth::check() || !in_array(Auth::user()->role, ['coordinator', 'staff', 'admin'], true)) {
            abort(403, 'Acesso negado. Você não tem permissão para gerenciar o catálogo.');
        }
    }

    /**
     * Exibir a grade de disciplinas de uma matriz (integrado na edição).
     */
    public function show(CurriculumMatrix $matrix)
    {
        $this->ensureAdmin();

        $matrix->load(['course', 'disciplinesForOffering.subject']);

        // Agrupar por período
        $disciplinesByPeriod = $matrix->disciplinesForOffering()
            ->orderBy('period')
            ->get()
            ->groupBy('period');

        // Obter todas as disciplinas disponíveis para vincular
        $availableSubjects = Subject::orderBy('name')->get();

        // Obter resumo
        $summary = $matrix->getSummary();

        return view('admin.matrices.disciplines', [
            'matrix' => $matrix,
            'disciplinesByPeriod' => $disciplinesByPeriod,
            'availableSubjects' => $availableSubjects,
            'summary' => $summary,
            'shifts' => [
                'MANHÃ' => 'Manhã (07:30 - 12:40) — 6 aulas',
                'TARDE' => 'Tarde (13:10 - 17:30) — 5 aulas',
                'NOITE' => 'Noite (18:30 - 22:00) — 4 aulas',
                'INTEGRAL' => 'Integral (07:30 - 17:30) — Manhã e Tarde',
            ],
            'offeringTypes' => [
                'OBRIGATORIA' => 'Obrigatória',
                'OPTATIVA' => 'Optativa',
                'ELETIVA' => 'Eletiva',
                'INTEGRADORA' => 'Integradora',
            ],
            'roomTypes' => [
                'SALA_COMUM' => 'Sala Comum',
                'LABORATORIO_INFORMATICA' => 'Laboratório de Informática',
                'LABORATORIO_ENGENHARIA' => 'Laboratório de Engenharia',
                'SALA_PRATICA' => 'Sala de Prática',
                'ESTUDIO_PRODUCAO' => 'Estúdio de Produção',
                'LABORATORIO_CIENCIAS' => 'Laboratório de Ciências',
            ],
        ]);
    }

    /**
     * Armazenar novo vínculo de disciplina.
     */
    public function store(Request $request, CurriculumMatrix $matrix)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'subject_id' => [
                'required',
                'integer',
                'exists:subjects,id',
            ],
            'period' => ['required', 'integer', 'min:1', 'max:8'],
            'shift' => ['required', Rule::in(['MANHÃ', 'TARDE', 'NOITE', 'INTEGRAL'])],
            'weekly_hours' => ['required', 'integer', 'min:1', 'max:30'],
            'offering_type' => ['required', Rule::in(['OBRIGATORIA', 'OPTATIVA', 'ELETIVA', 'INTEGRADORA'])],
            'allows_shared_class' => ['nullable', 'boolean'],
            'required_room_type' => [
                'nullable',
                Rule::in([
                    'SALA_COMUM',
                    'LABORATORIO_INFORMATICA',
                    'LABORATORIO_ENGENHARIA',
                    'SALA_PRATICA',
                    'ESTUDIO_PRODUCAO',
                    'LABORATORIO_CIENCIAS',
                ]),
            ],
            'max_consecutive_classes' => ['nullable', 'integer', 'min:1', 'max:6'],
        ]);

        // Validar se já existe vínculo neste período
        if (!CurriculumMatrixDiscipline::validateUniquePeriodDiscipline(
            $matrix->id,
            $validated['subject_id'],
            $validated['period']
        )) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Esta disciplina já está vinculada neste período da matriz.');
        }

        $link = CurriculumMatrixDiscipline::create([
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $validated['subject_id'],
            'period' => $validated['period'],
            'shift' => $validated['shift'],
            'weekly_hours' => $validated['weekly_hours'],
            'offering_type' => $validated['offering_type'],
            'allows_shared_class' => $validated['allows_shared_class'] ?? false,
            'required_room_type' => $validated['required_room_type'] ?? null,
            'max_consecutive_classes' => $validated['max_consecutive_classes'] ?? 2,
        ]);

        return redirect()->route('admin.matrices.disciplines.show', $matrix)
            ->with('success', 'Disciplina vinculada com sucesso!');
    }

    /**
     * Atualizar vínculo de disciplina.
     */
    public function update(Request $request, CurriculumMatrix $matrix, CurriculumMatrixDiscipline $discipline)
    {
        $this->ensureAdmin();

        // Garantir que a disciplina pertence à matriz
        if ($discipline->curriculum_matrix_id !== $matrix->id) {
            abort(404);
        }

        $validated = $request->validate([
            'period' => ['required', 'integer', 'min:1', 'max:8'],
            'shift' => ['required', Rule::in(['MANHÃ', 'TARDE', 'NOITE', 'INTEGRAL'])],
            'weekly_hours' => ['required', 'integer', 'min:1', 'max:30'],
            'offering_type' => ['required', Rule::in(['OBRIGATORIA', 'OPTATIVA', 'ELETIVA', 'INTEGRADORA'])],
            'allows_shared_class' => ['nullable', 'boolean'],
            'required_room_type' => [
                'nullable',
                Rule::in([
                    'SALA_COMUM',
                    'LABORATORIO_INFORMATICA',
                    'LABORATORIO_ENGENHARIA',
                    'SALA_PRATICA',
                    'ESTUDIO_PRODUCAO',
                    'LABORATORIO_CIENCIAS',
                ]),
            ],
            'max_consecutive_classes' => ['nullable', 'integer', 'min:1', 'max:6'],
        ]);

        // Se mudou o período, validar unicidade novamente
        if ($validated['period'] !== $discipline->period) {
            if (!CurriculumMatrixDiscipline::validateUniquePeriodDiscipline(
                $matrix->id,
                $discipline->subject_id,
                $validated['period'],
                $discipline->id
            )) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Esta disciplina já está vinculada no novo período.');
            }
        }

        $discipline->update([
            'period' => $validated['period'],
            'shift' => $validated['shift'],
            'weekly_hours' => $validated['weekly_hours'],
            'offering_type' => $validated['offering_type'],
            'allows_shared_class' => $validated['allows_shared_class'] ?? false,
            'required_room_type' => $validated['required_room_type'] ?? null,
            'max_consecutive_classes' => $validated['max_consecutive_classes'] ?? 2,
        ]);

        return redirect()->route('admin.matrices.disciplines.show', $matrix)
            ->with('success', 'Disciplina atualizada com sucesso!');
    }

    /**
     * Remover vínculo de disciplina.
     */
    public function destroy(Request $request, CurriculumMatrix $matrix, CurriculumMatrixDiscipline $discipline)
    {
        $this->ensureAdmin();

        // Garantir que a disciplina pertence à matriz
        if ($discipline->curriculum_matrix_id !== $matrix->id) {
            abort(404);
        }

        $subjectName = $discipline->subject->name ?? 'Disciplina';
        $discipline->delete();

        return redirect()->route('admin.matrices.disciplines.show', $matrix)
            ->with('success', "Vínculo de \"$subjectName\" removido com sucesso!");
    }

    /**
     * Editando inline - retornar formulário parcial para editar via modal/inline.
     */
    public function edit(CurriculumMatrix $matrix, CurriculumMatrixDiscipline $discipline)
    {
        $this->ensureAdmin();

        if ($discipline->curriculum_matrix_id !== $matrix->id) {
            abort(404);
        }

        $discipline->load('subject');

        return view('admin.matrices.disciplines.form', [
            'matrix' => $matrix,
            'discipline' => $discipline,
            'shifts' => [
                'MANHÃ' => 'Manhã (07:30 - 12:40) — 6 aulas',
                'TARDE' => 'Tarde (13:10 - 17:30) — 5 aulas',
                'NOITE' => 'Noite (18:30 - 22:00) — 4 aulas',
                'INTEGRAL' => 'Integral (07:30 - 17:30) — Manhã e Tarde',
            ],
            'offeringTypes' => [
                'OBRIGATORIA' => 'Obrigatória',
                'OPTATIVA' => 'Optativa',
                'ELETIVA' => 'Eletiva',
                'INTEGRADORA' => 'Integradora',
            ],
            'roomTypes' => [
                'SALA_COMUM' => 'Sala Comum',
                'LABORATORIO_INFORMATICA' => 'Laboratório de Informática',
                'LABORATORIO_ENGENHARIA' => 'Laboratório de Engenharia',
                'SALA_PRATICA' => 'Sala de Prática',
                'ESTUDIO_PRODUCAO' => 'Estúdio de Produção',
                'LABORATORIO_CIENCIAS' => 'Laboratório de Ciências',
            ],
        ]);
    }
}
