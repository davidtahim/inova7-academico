<?php

namespace App\Services;

use App\Models\CourseAudit;
use Illuminate\Support\Collection;

/**
 * Instrumento da auditoria de curso (QUA-INT-08 v.25) e cálculo do resultado previsto.
 *
 * Unidades pontuadas: cada indicador com peso próprio (ex.: 1.1, peso 2) e cada grupo (ex.: 1.11,
 * peso 2, com subitens SIM/NÃO). A nota do grupo é a média dos subitens (SIM = 5, NÃO = 1, ou a nota
 * de 1 a 5). Resultado = média ponderada das unidades avaliadas, fora as marcadas "não se aplica".
 * A regra de conversão dos subitens SIM/NÃO não consta do QUA-INT-08: o resultado é uma estimativa.
 */
class CourseAuditCatalog
{
    private array $data;

    public function __construct(string $catalog = 'qua-int-08-v25')
    {
        $this->data = json_decode((string) file_get_contents(database_path("data/auditoria/{$catalog}.json")), true);
    }

    public function source(): string
    {
        return $this->data['source'];
    }

    public function dimensions(): array
    {
        return $this->data['dimensions'];
    }

    /** @return Collection<string, array> indicadores por código */
    public function indicators(): Collection
    {
        return collect($this->data['indicators'])->keyBy('code');
    }

    public function indicator(string $code): ?array
    {
        return $this->indicators()->get($code);
    }

    /** Grupos (1.10, 1.11…) com peso e subitens. */
    public function groups(): Collection
    {
        return collect($this->data['groups'])->keyBy('code');
    }

    /** Unidades pontuadas: [code, name, weight, dimension, essential, indicators[]]. */
    public function units(): Collection
    {
        $groups = $this->groups();
        $units = [];
        foreach ($this->indicators() as $code => $indicator) {
            if ($indicator['group']) {
                $group = $groups[$indicator['group']];
                $units[$group['code']] ??= ['code' => $group['code'], 'name' => $group['name'], 'weight' => $group['weight'], 'dimension' => $indicator['dimension'], 'essential' => false, 'indicators' => []];
                $units[$group['code']]['indicators'][] = $code;
                $units[$group['code']]['essential'] = $units[$group['code']]['essential'] || $indicator['essential'];
            } else {
                $units[$code] = ['code' => $code, 'name' => $indicator['name'], 'weight' => $indicator['weight'] ?? 1, 'dimension' => $indicator['dimension'], 'essential' => $indicator['essential'], 'indicators' => [$code]];
            }
        }

        return collect($units);
    }

    public function band(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }
        foreach ($this->data['results'] as [$label, $min]) {
            if ($score >= $min - 0.0001) {
                return $label;
            }
        }

        return end($this->data['results'])[0];
    }

    /**
     * Resultado previsto da auditoria.
     *
     * @return array{score: ?float, band: ?string, rated: int, applicable: int, units: Collection, documents: array{total: int, delivered: int, pending: int}, alerts: array<int, string>}
     */
    public function evaluate(CourseAudit $audit): array
    {
        $entries = $audit->entries->keyBy('indicator');
        $indicators = $this->indicators();
        $sum = 0.0;
        $weights = 0;
        $alerts = [];

        $units = $this->units()->map(function ($unit) use ($entries, $indicators, &$sum, &$weights, &$alerts) {
            $scores = [];
            $applicable = 0;
            foreach ($unit['indicators'] as $code) {
                $entry = $entries->get($code);
                if ($entry?->not_applicable) {
                    continue;
                }
                $applicable++;
                if ($entry?->score) {
                    $scores[] = $entry->score;
                }
                $indicator = $indicators[$code];
                if ($indicator['essential'] && $entry?->score !== null && $entry->score < 3) {
                    $alerts[] = "{$code} {$indicator['name']} é imprescindível e está com " . ($indicator['kind'] === 'simnao' ? 'NÃO' : "nota {$entry->score}") . '.';
                }
            }
            $unit['applicable'] = $applicable > 0;
            // Grupo em andamento: média dos subitens já avaliados (marcado como parcial).
            $unit['score'] = $scores ? round(array_sum($scores) / count($scores), 2) : null;
            $unit['partial'] = $scores && count($scores) < $applicable;
            if ($unit['score'] !== null) {
                $sum += $unit['score'] * $unit['weight'];
                $weights += $unit['weight'];
            }

            return $unit;
        });

        $docs = $audit->documents->where('status', '!=', 'na');
        $score = $weights ? round($sum / $weights, 2) : null;

        return [
            'score' => $score,
            'band' => $this->band($score),
            'rated' => $units->where('score', '!==', null)->count(),
            'applicable' => $units->where('applicable', true)->count(),
            'units' => $units,
            'documents' => [
                'total' => $this->expectedDocuments($audit),
                'delivered' => $docs->where('status', 'delivered')->count(),
                'na' => $audit->documents->where('status', 'na')->count(),
            ],
            'alerts' => $alerts,
        ];
    }

    /** Documentos exigidos nos indicadores aplicáveis (fora os marcados "não se aplica"). */
    public function expectedDocuments(CourseAudit $audit): int
    {
        $na = $audit->entries->where('not_applicable', true)->pluck('indicator')->all();

        $required = $this->indicators()->reject(fn ($i) => in_array($i['code'], $na, true));

        return $required->sum(fn ($i) => count($i['documents']))
            - $audit->documents->where('status', 'na')->whereIn('indicator', $required->keys()->all())->count();
    }
}
