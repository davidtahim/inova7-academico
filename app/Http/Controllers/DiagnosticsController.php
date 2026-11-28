<?php

namespace App\Http\Controllers;

use App\Services\AvailabilityGrid;
use App\Services\ScheduleDiagnostics;
use Illuminate\Http\JsonResponse;

/**
 * Diagnóstico de inviabilidade (RF06): por que a grade do semestre não fecha e o que fazer.
 */
class DiagnosticsController extends Controller
{
    public function index(ScheduleDiagnostics $diagnostics)
    {
        $term = AvailabilityGrid::selectedTerm();
        abort_unless($term, 404);

        return view('allocation.diagnosis', ['term' => $term] + $diagnostics->diagnose($term));
    }

    /** Resumo para a grade global (selo no botão e aviso após o resolvedor). */
    public function data(ScheduleDiagnostics $diagnostics): JsonResponse
    {
        $term = AvailabilityGrid::selectedTerm();
        abort_unless($term, 404);
        $result = $diagnostics->diagnose($term);

        return response()->json([
            'summary' => $result['summary'],
            'top' => array_map(fn ($i) => ['level' => $i['level'], 'title' => $i['title']], array_slice($result['issues'], 0, 5)),
            'url' => route('allocation.diagnosis'),
        ]);
    }
}
