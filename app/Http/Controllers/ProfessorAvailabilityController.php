<?php

namespace App\Http\Controllers;

use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Services\AvailabilityGrid;
use App\Services\TeachingQualificationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProfessorAvailabilityController extends Controller
{
    private const ROOM_TYPE_LABELS = [
        'SALA_COMUM' => 'Sala comum',
        'LABORATORIO_INFORMATICA' => 'Lab. Informática',
        'LABORATORIO_ENGENHARIA' => 'Lab. Engenharia',
        'SALA_PRATICA' => 'Sala de prática',
        'ESTUDIO_PRODUCAO' => 'Estúdio',
        'LABORATORIO_CIENCIAS' => 'Lab. Ciências',
    ];

    /** /docente/disponibilidade: grade do próprio professor, sem id na URL. */
    public function myAvailability(Request $request, AvailabilityGrid $grid)
    {
        return $this->show($this->ownProfessor($request), $grid);
    }

    /** /docente/disciplinas: habilitação do próprio professor. */
    public function mySubjects(Request $request, TeachingQualificationCatalog $catalog)
    {
        return $this->showSubjects($this->ownProfessor($request), $catalog);
    }

    private function ownProfessor(Request $request): Professor
    {
        $professor = $request->user()->resolveProfessor();
        abort_unless($professor, 403, 'Seu usuário ainda não está vinculado a um cadastro de professor. Procure a coordenação.');

        return $professor;
    }

    /** Depois de salvar, o professor volta para /docente; a coordenação, para /cadastros. */
    private function backTo(Request $request, Professor $professor, string $page)
    {
        $own = $request->user()->isTeacher() && (int) $request->user()->resolveProfessor()?->id === (int) $professor->id;

        return $own ? redirect()->route('teacher.' . $page) : redirect()->route('admin.professors.' . $page, $professor);
    }

    public function show(Professor $professor, AvailabilityGrid $grid)
    {
        return view('admin.professors-availability', array_merge(
            $grid->build($professor, AvailabilityGrid::selectedTerm()),
            [
                'professor' => $professor,
                'isEditable' => Gate::allows('updateAvailability', $professor),
            ]
        ));
    }

    public function updateAvailability(Request $request, Professor $professor)
    {
        $term = AvailabilityGrid::selectedTerm();

        if (!$term) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Nenhum período acadêmico disponível.'], 400);
            }
            return redirect()->back()->with('error', 'Nenhum período acadêmico disponível.');
        }

        $validated = $request->validate([
            'availability_slots' => ['array'],
            'availability_slots.*.weekday' => ['integer', 'between:1,6'],
            'availability_slots.*.starts_at' => ['date_format:H:i'],
            'availability_slots.*.ends_at' => ['date_format:H:i'],
            'availability_slots.*.preference' => ['string', 'in:preferred,available,unavailable'],
        ]);

        \Log::info('updateAvailability chamado', [
            'professor_id' => $professor->id,
            'term_id' => $term->id,
            'slots_count' => count($validated['availability_slots'] ?? []),
            'slots' => $validated['availability_slots'] ?? []
        ]);

        // Remover todas as disponibilidades do período atual
        ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $term->id)
            ->delete();

        // Adicionar novas disponibilidades
        foreach ($validated['availability_slots'] ?? [] as $slot) {
            $created = ProfessorAvailability::create([
                'professor_id' => $professor->id,
                'academic_term_id' => $term->id,
                'weekday' => $slot['weekday'],
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['ends_at'],
                'preference' => $slot['preference'],
            ]);
            \Log::info('Slot criado', $created->toArray());
        }

        $allSlots = ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $term->id)
            ->get();

        \Log::info('Total de slots após salvar', ['count' => $allSlots->count()]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Disponibilidade atualizada com sucesso!']);
        }

        return $this->backTo($request, $professor, 'availability')
            ->with('success', 'Disponibilidade atualizada com sucesso!');
    }

    public function showSubjects(Professor $professor, TeachingQualificationCatalog $catalog)
    {
        $selectedSubjects = $professor->subjects()->orderBy('name')->get();
        $data = $catalog->build();

        return view('admin.professors-subjects', [
            'professor' => $professor,
            'selectedSubjects' => $selectedSubjects,
            'selectedIds' => $selectedSubjects->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'matrices' => $data['matrices'],
            'otherSubjects' => $data['others'],
            'selectedCount' => $selectedSubjects->count(),
            'totalSubjects' => $data['total_subjects'],
            'unselectedCount' => max(0, $data['total_subjects'] - $selectedSubjects->count()),
            'isEditable' => Gate::allows('updateSubjects', $professor),
            'roomTypes' => self::ROOM_TYPE_LABELS,
        ]);
    }

    public function updateSubjects(Request $request, Professor $professor)
    {
        $validated = $request->validate([
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        $subjectIds = collect($validated['subject_ids'] ?? [])->unique()->all();
        $professor->subjects()->sync($subjectIds);

        return $this->backTo($request, $professor, 'subjects')
            ->with('success', 'Disciplinas atualizadas com sucesso!');
    }
}
