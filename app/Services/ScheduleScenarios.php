<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Professor;
use App\Models\ScheduleScenario;
use App\Models\ScheduleSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Versões da grade (RF07): retrato, restauração, exportação e importação.
 *
 * O arquivo identifica cada disciplina/turma pelo código da turma + código da
 * disciplina e cada professor pela matrícula/chapa/nome, não por ids do banco:
 * assim uma versão exportada da máquina local pode ser importada em produção.
 */
class ScheduleScenarios
{
    public const FORMAT = 'inova7-grade';

    public const VERSION = 1;

    /** Quantas versões automáticas guardar por semestre (as manuais nunca são apagadas). */
    public const KEEP_AUTO = 10;

    public function __construct(private ScheduleAllocator $allocator, private ScheduleScorer $scorer)
    {
    }

    /** Retrato da grade atual no formato de arquivo. */
    public function snapshot(AcademicTerm $term): array
    {
        $offerings = ClassOffering::with(['subject', 'slots', 'assignments.professor'])
            ->where('academic_term_id', $term->id)->where('occurs', true)
            ->orderBy('class_code')->get();

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'term' => $term->code,
            'exported_at' => now()->toIso8601String(),
            'items' => $offerings->map(function (ClassOffering $o) {
                $current = $this->allocator->currentInput($o);
                $assignment = $o->assignments->first();
                $professor = $assignment?->professor;

                return [
                    'class' => $o->class_code,
                    'subject_code' => $o->subject?->code,
                    'subject' => $o->subject?->name,
                    'shift' => $o->shift,
                    'professor' => $professor ? array_filter([
                        'name' => $professor->name,
                        'registration' => $professor->registration,
                        'chapa' => $professor->chapa,
                    ]) : null,
                    'confirmed' => $assignment?->status === 'confirmed',
                    'room' => $current['room'],
                    'placements' => (object) $current['placements'],
                ];
            })->values()->all(),
        ];
    }

    public function save(AcademicTerm $term, string $name, ?User $user, string $kind = 'manual', ?array $payload = null): ScheduleScenario
    {
        $payload ??= $this->snapshot($term);
        $score = $kind === 'import' ? null : $this->scorer->score($term);
        $classes = collect($payload['items'])->sum(fn ($i) => collect((array) $i['placements'])->sum(fn ($l) => count((array) $l)));

        $scenario = ScheduleScenario::create([
            'academic_term_id' => $term->id,
            'name' => Str::limit(trim($name) ?: 'Versão sem nome', 120, ''),
            'kind' => $kind,
            'payload' => json_decode(json_encode($payload), true),
            'offerings_count' => count($payload['items']),
            'classes_count' => $classes,
            'score' => $score['total'] ?? null,
            'hard' => $score['hard'] ?? null,
            'created_by' => $user?->id,
        ]);

        if ($kind === 'auto') {
            ScheduleScenario::where('academic_term_id', $term->id)->where('kind', 'auto')
                ->orderByDesc('id')->skip(self::KEEP_AUTO)->take(PHP_INT_MAX)->get()->each->delete();
        }

        return $scenario;
    }

    /**
     * Valida um arquivo importado. Devolve [payload, erros].
     *
     * @return array{0: ?array, 1: array<int, string>}
     */
    public function parse(string $json, AcademicTerm $term): array
    {
        $data = json_decode($json, true);
        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT || ! is_array($data['items'] ?? null)) {
            return [null, ['O arquivo não é uma versão de grade do Inova7 (exporte-o pela tela de versões).']];
        }
        if ((int) ($data['version'] ?? 0) > self::VERSION) {
            return [null, ['O arquivo foi gerado por uma versão mais nova do sistema.']];
        }
        $errors = [];
        foreach ($data['items'] as $i => $item) {
            if (! is_array($item) || empty($item['class']) || (empty($item['subject_code']) && empty($item['subject']))) {
                $errors[] = 'Item ' . ($i + 1) . ': falta a turma ou a disciplina.';
            }
            foreach ((array) ($item['placements'] ?? []) as $day => $letters) {
                if ((int) $day < 1 || (int) $day > 6 || ! is_array($letters)) {
                    $errors[] = 'Item ' . ($i + 1) . ': dia inválido.';
                    break;
                }
            }
        }

        return [$errors ? null : $data, array_slice($errors, 0, 10)];
    }

    /**
     * Restaura uma versão: antes, guarda a grade atual como versão automática.
     *
     * @return array{saved: int, unchanged: int, rejected: array<int, string>, unmatched: array<int, string>, backup: ScheduleScenario, score_after: int, hard_after: int}
     */
    public function restore(ScheduleScenario $scenario, ?User $user): array
    {
        $term = $scenario->term;
        $backup = $this->save($term, 'Antes de restaurar "' . $scenario->name . '"', $user, 'auto');

        [$items, $unmatched] = $this->resolve($term, $scenario->payload['items'] ?? []);
        $result = $this->applyChanges($term, $items);
        $after = $this->scorer->score($term);

        return $result + ['unmatched' => $unmatched, 'backup' => $backup, 'score_after' => $after['total'], 'hard_after' => $after['hard']];
    }

    /**
     * Itens do arquivo → ids deste banco (oferta e professor).
     *
     * @return array{0: array<int, array>, 1: array<int, string>}
     */
    public function resolve(AcademicTerm $term, array $items): array
    {
        $offerings = ClassOffering::with('subject')->where('academic_term_id', $term->id)->where('occurs', true)->get();
        $byCode = $offerings->groupBy(fn ($o) => $o->class_code . '|' . $o->subject?->code);
        $byName = $offerings->groupBy(fn ($o) => $o->class_code . '|' . $this->key($o->subject?->name));
        $professors = Professor::all();

        $resolved = [];
        $unmatched = [];
        foreach ($items as $item) {
            $offering = $byCode->get($item['class'] . '|' . ($item['subject_code'] ?? ''))?->first()
                ?? $byName->get($item['class'] . '|' . $this->key($item['subject'] ?? ''))?->first();
            if (! $offering) {
                $unmatched[] = "{$item['class']} · " . ($item['subject'] ?? $item['subject_code']) . ': disciplina/turma não existe neste semestre.';

                continue;
            }

            $professorId = null;
            if (! empty($item['professor'])) {
                $p = (array) $item['professor'];
                $professor = (! empty($p['registration']) ? $professors->firstWhere('registration', $p['registration']) : null)
                    ?? (! empty($p['chapa']) ? $professors->firstWhere('chapa', $p['chapa']) : null)
                    ?? $professors->first(fn ($x) => $this->key($x->name) === $this->key($p['name'] ?? ''));
                if (! $professor) {
                    $unmatched[] = "{$item['class']} · {$offering->subject?->name}: professor " . ($p['name'] ?? '?') . ' não encontrado; a aula fica sem professor.';
                }
                $professorId = $professor?->id;
            }

            $resolved[] = [
                'id' => $offering->id,
                'professor_id' => $professorId,
                'room' => $item['room'] ?? null,
                'placements' => collect((array) ($item['placements'] ?? []))->map(fn ($l) => array_values((array) $l))->all(),
                'confirmed' => (bool) ($item['confirmed'] ?? false),
            ];
        }

        return [$resolved, $unmatched];
    }

    /**
     * Grava só o que mudou, revalidando cada disciplina/turma (mesma regra do resolvedor).
     * Limpa os horários das alteradas antes, para a ordem de gravação não gerar choques
     * com o estado antigo.
     *
     * @param  array<int, array{id: int, professor_id: ?int, room: ?string, placements: array, confirmed?: bool}>  $items
     * @return array{saved: int, unchanged: int, rejected: array<int, string>}
     */
    public function applyChanges(AcademicTerm $term, array $items): array
    {
        $offerings = ClassOffering::with(['slots', 'assignments', 'subject', 'matrixDiscipline'])
            ->where('academic_term_id', $term->id)
            ->whereIn('id', array_column($items, 'id'))
            ->get()->keyBy('id');

        $changes = collect($items)->filter(function ($item) use ($offerings) {
            $offering = $offerings->get($item['id']);
            if (! $offering) {
                return false;
            }
            $current = $this->allocator->currentInput($offering);
            $placements = collect($item['placements'] ?? [])->map(fn ($l) => collect($l)->sort()->values()->all())->filter()->sortKeys()->all();
            $confirmedNow = $offering->assignments->first()?->status === 'confirmed';

            return (int) $current['professor_id'] !== (int) ($item['professor_id'] ?? 0)
                || ($current['room'] ?? null) !== ($item['room'] ?? null)
                || $current['placements'] != $placements
                || (array_key_exists('confirmed', $item) && $item['professor_id'] && $confirmedNow !== $item['confirmed']);
        })->values();

        $saved = 0;
        $rejected = [];
        DB::transaction(function () use ($changes, $offerings, &$saved, &$rejected) {
            ScheduleSlot::whereIn('class_offering_id', $changes->pluck('id'))->delete();

            foreach ($changes as $item) {
                $offering = $offerings->get($item['id'])->load('slots', 'assignments');
                if (array_key_exists('confirmed', $item)) {
                    $status = $item['confirmed'] ? 'confirmed' : 'planned';
                } else {
                    // Resolvedor: mantém "confirmado" só se o professor confirmado continuar.
                    $confirmed = $offering->assignments->firstWhere('status', 'confirmed');
                    $status = $confirmed && (int) $confirmed->professor_id === (int) ($item['professor_id'] ?? 0) ? 'confirmed' : 'planned';
                }

                $result = $this->allocator->apply($offering, [
                    'professor_id' => $item['professor_id'] ?? null,
                    'placements' => $item['placements'] ?? [],
                    'room' => $item['room'] ?? null,
                ], $status);

                $result['saved'] ? $saved++ : $rejected[] = "{$offering->class_code} · {$offering->subject->name}: " . $result['errors'][0];
            }
        });

        return ['saved' => $saved, 'unchanged' => count($items) - $changes->count(), 'rejected' => $rejected];
    }

    public function filename(ScheduleScenario|AcademicTerm $source): string
    {
        $term = $source instanceof AcademicTerm ? $source : $source->term;
        $name = $source instanceof AcademicTerm ? 'atual' : Str::slug($source->name);

        return 'grade-' . Str::slug($term->code) . '-' . $name . '-' . now()->format('Ymd-His') . '.json';
    }

    private function key(?string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $text)));
    }
}
