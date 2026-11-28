<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Services\AvailabilityGrid;
use App\Services\TeachingQualificationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                // Semestre concluído: a grade fica só para consulta.
                'isEditable' => Gate::allows('updateAvailability', $professor) && ! AvailabilityGrid::selectedTerm()?->isClosed(),
            ]
        ));
    }

    /**
     * Grava a grade inteira do semestre exibido na tela (academic_term_id), não do
     * semestre da sessão: se o seletor mudar em outra aba, nada é gravado no lugar errado.
     */
    public function updateAvailability(Request $request, Professor $professor)
    {
        $validated = $request->validate([
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'availability_slots' => ['array'],
            'availability_slots.*.weekday' => ['required', 'integer', 'between:1,6'],
            'availability_slots.*.starts_at' => ['required', 'date_format:H:i'],
            'availability_slots.*.ends_at' => ['required', 'date_format:H:i'],
            'availability_slots.*.preference' => ['required', 'string', 'in:preferred,available,unavailable'],
        ]);
        $term = ! empty($validated['academic_term_id']) ? AcademicTerm::find($validated['academic_term_id']) : AvailabilityGrid::selectedTerm();

        if ($term?->isClosed()) {
            return \App\Http\Middleware\EnsureTermIsOpen::refuse($request, $term);
        }

        if (! $term) {
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'Nenhum semestre cadastrado.'], 422)
                : back()->with('error', 'Nenhum semestre cadastrado.');
        }

        $slots = collect($validated['availability_slots'] ?? [])
            ->reject(fn ($slot) => $slot['preference'] === 'unavailable')
            ->unique(fn ($slot) => $slot['weekday'] . '|' . $slot['starts_at']);

        DB::transaction(function () use ($professor, $term, $slots) {
            ProfessorAvailability::where('professor_id', $professor->id)->where('academic_term_id', $term->id)->delete();
            foreach ($slots as $slot) {
                ProfessorAvailability::create([
                    'professor_id' => $professor->id,
                    'academic_term_id' => $term->id,
                    'weekday' => $slot['weekday'],
                    'starts_at' => $slot['starts_at'],
                    'ends_at' => $slot['ends_at'],
                    'preference' => $slot['preference'],
                ]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'saved' => $slots->count(),
                'term' => $term->code,
                'saved_at' => now()->format('H:i'),
                'message' => 'Disponibilidade salva.',
            ]);
        }

        return $this->backTo($request, $professor, 'availability')->with('success', 'Disponibilidade salva.');
    }

    /** Copia a grade de outro semestre para o semestre exibido (substitui a atual). */
    public function copyAvailability(Request $request, Professor $professor)
    {
        $data = $request->validate([
            'from_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'to_term_id' => ['required', 'integer', 'exists:academic_terms,id', 'different:from_term_id'],
        ]);
        $from = AcademicTerm::find($data['from_term_id']);
        $to = AcademicTerm::find($data['to_term_id']);
        if ($to->isClosed()) {
            return \App\Http\Middleware\EnsureTermIsOpen::refuse($request, $to);
        }

        $source = ProfessorAvailability::where('professor_id', $professor->id)->where('academic_term_id', $from->id)->get();
        DB::transaction(function () use ($professor, $to, $source) {
            ProfessorAvailability::where('professor_id', $professor->id)->where('academic_term_id', $to->id)->delete();
            foreach ($source as $item) {
                ProfessorAvailability::create([
                    'professor_id' => $professor->id, 'academic_term_id' => $to->id, 'weekday' => $item->weekday,
                    'starts_at' => substr((string) $item->starts_at, 0, 5), 'ends_at' => substr((string) $item->ends_at, 0, 5), 'preference' => $item->preference,
                ]);
            }
        });
        session(['selected_academic_term_id' => $to->id]);

        return $this->backTo($request, $professor, 'availability')
            ->with('success', "Grade de {$from->code} copiada para {$to->code} ({$source->count()} horários). Confira e ajuste se precisar.");
    }

    public function showSubjects(Professor $professor, TeachingQualificationCatalog $catalog)
    {
        $selectedSubjects = $professor->subjects()->orderBy('name')->get();
        // Na área do professor, só as matrizes dos cursos em que ele atua (definidos pelo Admin TI).
        $courses = request()->routeIs('teacher.*') ? $professor->courses()->orderBy('name')->get() : collect();
        $data = $catalog->build($courses->isNotEmpty() ? $courses->pluck('id')->all() : null, $selectedSubjects->pluck('id')->all());

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
            'courseFilter' => $courses,
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
