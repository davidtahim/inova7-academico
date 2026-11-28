<?php

namespace App\Http\Controllers;

use App\Models\ClassOffering;
use App\Models\ScheduleSlot;
use App\Services\AvailabilityGrid;
use App\Services\ScheduleAllocator;
use App\Services\ScheduleScorer;
use App\Services\SolverProblemBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Resolvedor automático (RF05): o servidor entrega o problema, o navegador resolve
 * (Web Worker) e o servidor revalida cada oferta antes de gravar a solução.
 */
class SolverController extends Controller
{
    public function problem(SolverProblemBuilder $builder): JsonResponse
    {
        $term = AvailabilityGrid::selectedTerm();
        abort_unless($term, 404);

        return response()->json($builder->build($term));
    }

    public function apply(Request $request, ScheduleAllocator $allocator, ScheduleScorer $scorer): JsonResponse
    {
        $term = AvailabilityGrid::selectedTerm();
        abort_unless($term, 404);

        $validated = $request->validate([
            'solution' => ['required', 'array', 'max:2000'],
            'solution.*.id' => ['required', 'integer'],
            'solution.*.professor_id' => ['nullable', 'integer', 'exists:professors,id'],
            'solution.*.room' => ['nullable', 'string', 'exists:rooms,code'],
            'solution.*.placements' => ['nullable', 'array'],
        ]);

        $before = $scorer->score($term);
        $offerings = ClassOffering::with(['slots', 'assignments', 'subject', 'matrixDiscipline'])
            ->where('academic_term_id', $term->id)
            ->whereIn('id', collect($validated['solution'])->pluck('id'))
            ->get()->keyBy('id');

        // Só o que mudou é regravado.
        $changes = collect($validated['solution'])->filter(function ($item) use ($offerings, $allocator) {
            $offering = $offerings->get($item['id']);
            if (! $offering || $offering->isDistance()) {
                return false;
            }
            $current = $allocator->currentInput($offering);
            $placements = collect($item['placements'] ?? [])->map(fn ($l) => collect($l)->sort()->values()->all())->sortKeys()->all();

            return (int) $current['professor_id'] !== (int) ($item['professor_id'] ?? 0)
                || ($current['room'] ?? null) !== ($item['room'] ?? null)
                || $current['placements'] != $placements;
        })->values();

        $saved = 0;
        $rejected = [];
        DB::transaction(function () use ($changes, $offerings, $allocator, &$saved, &$rejected) {
            // Limpa antes, para a ordem de gravação não gerar choques com o estado antigo.
            ScheduleSlot::whereIn('class_offering_id', $changes->pluck('id'))->delete();

            foreach ($changes as $item) {
                $offering = $offerings->get($item['id'])->load('slots', 'assignments');
                $confirmed = $offering->assignments->firstWhere('status', 'confirmed');
                $status = $confirmed && (int) $confirmed->professor_id === (int) ($item['professor_id'] ?? 0) ? 'confirmed' : 'planned';

                $result = $allocator->apply($offering, [
                    'professor_id' => $item['professor_id'] ?? null,
                    'placements' => $item['placements'] ?? [],
                    'room' => $item['room'] ?? null,
                ], $status);

                $result['saved'] ? $saved++ : $rejected[] = "{$offering->class_code} · {$offering->subject->name}: " . $result['errors'][0];
            }
        });

        $after = $scorer->score($term);

        return response()->json([
            'saved' => $saved,
            'unchanged' => count($validated['solution']) - $changes->count(),
            'rejected' => $rejected,
            'score_before' => $before['total'],
            'score_after' => $after['total'],
            'hard_after' => $after['hard'],
        ]);
    }
}
