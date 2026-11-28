<?php

namespace App\Http\Controllers;

use App\Models\ScheduleScenario;
use App\Services\AvailabilityGrid;
use App\Services\ScheduleScenarios;
use App\Services\ScheduleScorer;
use Illuminate\Http\Request;

/**
 * Versões da grade (RF07): salvar, restaurar, exportar e importar cenários.
 */
class ScenarioController extends Controller
{
    private function term()
    {
        $term = AvailabilityGrid::selectedTerm();
        abort_unless($term, 404);

        return $term;
    }

    private function ensureSameTerm(ScheduleScenario $scenario): void
    {
        abort_unless((int) $scenario->academic_term_id === (int) $this->term()->id, 404);
    }

    public function index(ScheduleScorer $scorer)
    {
        $term = $this->term();

        return view('allocation.scenarios', [
            'term' => $term,
            'current' => $scorer->score($term),
            'scenarios' => ScheduleScenario::with('author')->where('academic_term_id', $term->id)->latest('id')->get(),
        ]);
    }

    public function store(Request $request, ScheduleScenarios $scenarios)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $scenario = $scenarios->save($this->term(), $data['name'], $request->user());

        return redirect()->route('allocation.scenarios')->with('success', "Versão \"{$scenario->name}\" salva.");
    }

    public function restore(Request $request, ScheduleScenario $scenario, ScheduleScenarios $scenarios)
    {
        $this->ensureSameTerm($scenario);
        $r = $scenarios->restore($scenario, $request->user(), \App\Support\CourseScope::ids());

        $message = "Versão \"{$scenario->name}\" restaurada: {$r['saved']} disciplinas regravadas, {$r['unchanged']} já estavam iguais. "
            . "Pontos agora: " . number_format($r['score_after'], 0, ',', '.') . " ({$r['hard_after']} rígidas). "
            . 'A grade anterior ficou guardada como versão automática.';

        return redirect()->route('allocation.scenarios')
            ->with($r['rejected'] || $r['unmatched'] ? 'warning' : 'success', $message)
            ->with('restore_problems', array_merge($r['rejected'], $r['unmatched']));
    }

    public function download(ScheduleScenario $scenario, ScheduleScenarios $scenarios)
    {
        $this->ensureSameTerm($scenario);

        return $this->json($scenario->payload, $scenarios->filename($scenario));
    }

    public function export(ScheduleScenarios $scenarios)
    {
        $term = $this->term();

        return $this->json($scenarios->snapshot($term), $scenarios->filename($term));
    }

    public function import(Request $request, ScheduleScenarios $scenarios)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'name' => ['nullable', 'string', 'max:120'],
        ], ['file.required' => 'Escolha o arquivo .json exportado da tela de versões.']);
        $term = $this->term();

        [$payload, $errors] = $scenarios->parse((string) file_get_contents($request->file('file')->getRealPath()), $term);
        if (! $payload) {
            return back()->with('error', implode(' ', $errors));
        }

        $name = $request->input('name') ?: pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
        $scenario = $scenarios->save($term, $name, $request->user(), 'import', $payload);
        $note = ($payload['term'] ?? null) !== $term->code ? " Atenção: o arquivo é do semestre {$payload['term']} e será aplicado em {$term->code}." : '';

        return redirect()->route('allocation.scenarios')
            ->with('success', "Arquivo importado como a versão \"{$scenario->name}\". Confira e clique em Restaurar para aplicá-la.{$note}");
    }

    public function destroy(ScheduleScenario $scenario)
    {
        $this->ensureSameTerm($scenario);
        $scenario->delete();

        return redirect()->route('allocation.scenarios')->with('success', "Versão \"{$scenario->name}\" excluída.");
    }

    private function json(array $payload, string $filename)
    {
        return response()->streamDownload(
            fn () => print(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $filename,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }
}
