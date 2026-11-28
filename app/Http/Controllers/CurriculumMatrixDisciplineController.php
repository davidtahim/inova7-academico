<?php

namespace App\Http\Controllers;

use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CurriculumMatrixDisciplineController extends Controller
{
    /** Vínculo matriz ↔ disciplina: Administrador de TI e Coordenador. */
    private function ensureCanManage(): void
    {
        abort_unless(Gate::allows('manage-catalog'), 403, 'Apenas administradores e coordenadores podem gerenciar vínculos de disciplinas.');
    }

    private function ensureBelongsToMatrix(CurriculumMatrix $matrix, CurriculumMatrixDiscipline $discipline): void
    {
        abort_unless((int) $discipline->curriculum_matrix_id === (int) $matrix->getKey(), 404);
    }

    /** Opções dos formulários de vínculo (também usadas na tela de disciplinas). */
    public static function options(): array
    {
        return [
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
            'roomTypes' => \App\Models\Room::TYPES,
        ];
    }

    /**
     * A grade da matriz agora vive na tela de disciplinas (visão "por matriz").
     */
    public function show(CurriculumMatrix $matrix)
    {
        $this->ensureCanManage();

        return redirect()->route('admin.subjects.index', ['matriz' => $matrix->id]);
    }

    /** Volta para a grade da matriz, no período alterado. */
    private function backToMatrix(CurriculumMatrix $matrix, ?int $period = null)
    {
        return redirect()->to(route('admin.subjects.index', ['matriz' => $matrix->id]) . ($period ? '#periodo-' . $period : ''));
    }

    /**
     * Armazenar novo vínculo de disciplina.
     */
    public function store(Request $request, CurriculumMatrix $matrix)
    {
        $this->ensureCanManage();

        $validated = $request->validate([
            'subject_id' => [
                'required',
                'integer',
                'exists:subjects,id',
            ],
            'period' => ['required', 'integer', 'min:1', 'max:8'],
            'shift' => ['required', Rule::in(['MANHÃ', 'TARDE', 'NOITE', 'INTEGRAL'])],
            'weekly_hours' => ['required', 'integer', 'min:0', 'max:30'],
            'offering_type' => ['required', Rule::in(['OBRIGATORIA', 'OPTATIVA', 'ELETIVA', 'INTEGRADORA'])],
            'allows_shared_class' => ['nullable', 'boolean'],
            'required_room_type' => [
                'nullable',
                Rule::in(array_keys(\App\Models\Room::TYPES)),
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

        // Art. 8º III: sem tipo de sala informado, disciplina com carga prática exige laboratório.
        if (empty($validated['required_room_type']) && (int) Subject::whereKey($validated['subject_id'])->value('practice_hours') > 0) {
            $validated['required_room_type'] = 'LABORATORIO_INFORMATICA';
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

        return $this->backToMatrix($matrix, $link->period)
            ->with('success', 'Disciplina vinculada com sucesso!');
    }

    /**
     * Atualizar vínculo de disciplina.
     */
    public function update(Request $request, CurriculumMatrix $matrix, CurriculumMatrixDiscipline $discipline)
    {
        $this->ensureCanManage();

        $this->ensureBelongsToMatrix($matrix, $discipline);

        $validated = $request->validate([
            'period' => ['required', 'integer', 'min:1', 'max:8'],
            'shift' => ['required', Rule::in(['MANHÃ', 'TARDE', 'NOITE', 'INTEGRAL'])],
            'weekly_hours' => ['required', 'integer', 'min:0', 'max:30'],
            'offering_type' => ['required', Rule::in(['OBRIGATORIA', 'OPTATIVA', 'ELETIVA', 'INTEGRADORA'])],
            'allows_shared_class' => ['nullable', 'boolean'],
            'required_room_type' => [
                'nullable',
                Rule::in(array_keys(\App\Models\Room::TYPES)),
            ],
            'max_consecutive_classes' => ['nullable', 'integer', 'min:1', 'max:6'],
        ]);

        // Se mudou o período, validar unicidade novamente
        if ((int) $validated['period'] !== $discipline->period) {
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

        return $this->backToMatrix($matrix, (int) $validated['period'])
            ->with('success', 'Vínculo atualizado com sucesso!');
    }

    /**
     * Remover vínculo de disciplina.
     */
    public function destroy(Request $request, CurriculumMatrix $matrix, CurriculumMatrixDiscipline $discipline)
    {
        $this->ensureCanManage();

        $this->ensureBelongsToMatrix($matrix, $discipline);

        $subjectName = $discipline->subject->name ?? 'Disciplina';
        $discipline->delete();

        return $this->backToMatrix($matrix, $discipline->period)
            ->with('success', "Vínculo de \"$subjectName\" removido com sucesso!");
    }
}
