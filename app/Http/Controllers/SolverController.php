<?php

namespace App\Http\Controllers;

use App\Services\AvailabilityGrid;
use App\Services\ScheduleScenarios;
use App\Services\ScheduleScorer;
use App\Services\SolverProblemBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function apply(Request $request, ScheduleScenarios $scenarios, ScheduleScorer $scorer): JsonResponse
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
        // Rede de segurança: a grade de antes fica guardada como versão automática.
        $scenarios->save($term, 'Antes de aplicar o resolvedor', $request->user(), 'auto');
        $result = $scenarios->applyChanges($term, $validated['solution']);

        $after = $scorer->score($term);

        return response()->json([
            'saved' => $result['saved'],
            'unchanged' => $result['unchanged'],
            'rejected' => $result['rejected'],
            'score_before' => $before['total'],
            'score_after' => $after['total'],
            'hard_after' => $after['hard'],
        ]);
    }
}
