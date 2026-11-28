<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Room;

/**
 * Diagnóstico de inviabilidade e assistente de resolução (RF06).
 *
 * Em vez de só dizer "faltam 12 aulas", procura a causa raiz que impede a grade de
 * fechar — antes mesmo de rodar o resolvedor — e sugere o que fazer. Usa o mesmo
 * problema do resolvedor (SolverProblemBuilder), então as duas partes enxergam a
 * mesma realidade: habilitações, professores confirmados, salas e disponibilidade.
 *
 * Níveis:
 *  - blocker: matematicamente impossível fechar sem mudar algum dado;
 *  - risk:    possível, mas apertado ou contra os limites da coordenação;
 *  - info:    dado incompleto que deixa o resultado menos confiável.
 */
class ScheduleDiagnostics
{
    public const DAYS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];

    public const SHIFT_LABELS = ['MANHÃ' => 'manhã', 'TARDE' => 'tarde', 'NOITE' => 'noite', 'INTEGRAL' => 'integral'];

    private array $problem;

    private array $professors;

    /** @var array<int, array> */
    private array $issues = [];

    /** @var array<string, array<int, array>> disciplinas sem ninguém habilitado, por disciplina */
    private array $orphans = [];

    public function __construct(private SolverProblemBuilder $builder, private ScheduleAllocator $allocator)
    {
    }

    /**
     * @return array{summary: array, issues: array<int, array>, contention: array<string, array>}
     */
    public function diagnose(AcademicTerm $term): array
    {
        $this->problem = $this->builder->build($term);
        $this->professors = collect($this->problem['professors'])->keyBy('id')->all();
        $this->issues = [];
        $this->orphans = [];

        $offerings = collect($this->problem['offerings']);
        $variable = $offerings->where('frozen', false)->values();

        foreach ($variable as $o) {
            $this->checkOffering($o);
        }
        $this->flushOrphans();
        $this->checkProfessors($variable);
        $this->checkClasses($offerings);
        $this->checkRooms($variable);
        $this->checkMissingGrids($variable);

        $order = ['blocker' => 0, 'risk' => 1, 'info' => 2];
        usort($this->issues, fn ($a, $b) => [$order[$a['level']], -$a['classes']] <=> [$order[$b['level']], -$b['classes']]);

        $placed = fn ($o) => array_sum(array_map('count', $o['placements']));
        $missing = $variable->sum(fn ($o) => max(0, $o['expected'] - $placed($o)));

        return [
            'summary' => [
                'term' => $this->problem['term'],
                'offerings' => $variable->count(),
                'classes' => $variable->sum('expected'),
                'missing' => $missing,
                'blockers' => count(array_filter($this->issues, fn ($i) => $i['level'] === 'blocker')),
                'risks' => count(array_filter($this->issues, fn ($i) => $i['level'] === 'risk')),
                'infos' => count(array_filter($this->issues, fn ($i) => $i['level'] === 'info')),
                // Classes atingidas por algum bloqueio: o que não fecha enquanto o dado não mudar.
                'blocked_classes' => collect($this->issues)->where('level', 'blocker')->sum('classes'),
            ],
            'issues' => array_values($this->issues),
            'contention' => $this->contention($variable),
        ];
    }

    // ------------------------------------------------------------------ verificações

    /** Uma disciplina-turma isolada: alguém pode dar? Em horários suficientes? */
    private function checkOffering(array $o): void
    {
        $label = $this->offeringLabel($o);
        $shift = $this->shiftLabel($o['shift']);
        $candidates = $o['fixedProfessor'] ? [$o['fixedProfessor']] : $o['candidates'];

        if ($candidates === []) {
            $this->orphans[$o['subjectKey']][] = $o; // agrupado por disciplina em flushOrphans()

            return;
        }

        if ($o['rooms'] === []) {
            $this->add('blocker', 'no_room', "{$label}: nenhuma sala do tipo exigido",
                'A disciplina exige um tipo de sala que não existe (ou está inativo) no cadastro de salas.',
                [$o], [['label' => 'Ajustar o tipo de sala na matriz', 'route' => ['admin.matrices.index', []]]]);
        }

        $cells = $this->shiftCells($o['shift']);
        $free = array_values(array_filter($cells, fn ($cell) => $this->anyAvailable($candidates, $cell)));
        if (count($free) >= $o['expected']) {
            return;
        }

        $names = array_map(fn ($id) => $this->professorName($id), $candidates);
        if (count($candidates) === 1) {
            $who = $o['fixedProfessor'] ? "o professor confirmado ({$names[0]})" : "o único professor habilitado ({$names[0]})";
            $detail = $free === []
                ? ucfirst($who) . " está indisponível em todos os horários da {$shift}."
                : ucfirst($who) . ' está disponível só em ' . $this->cellList($free) . " — {$this->plural(count($free), 'horário', 'horários')} para {$this->plural($o['expected'], 'aula', 'aulas')}.";
        } else {
            $detail = 'Os ' . count($candidates) . ' professores habilitados (' . implode(', ', $names) . ') juntos cobrem só '
                . $this->plural(count($free), 'horário', 'horários') . " da {$shift}" . ($free ? ' (' . $this->cellList($free) . ')' : '')
                . ' para ' . $this->plural($o['expected'], 'aula', 'aulas') . '.';
        }

        $actions = array_map(fn ($id) => ['label' => 'Disponibilidade de ' . $this->professorName($id), 'route' => ['admin.professors.availability', [$id]]], array_slice($candidates, 0, 3));
        if ($o['fixedProfessor'] && count($o['candidates']) > 1) {
            $others = array_values(array_diff($o['candidates'], [$o['fixedProfessor']]));
            $detail .= ' Também estão habilitados: ' . implode(', ', array_map(fn ($id) => $this->professorName($id), $others)) . ' — desconfirmar o professor deixa o resolvedor trocar.';
            $actions[] = ['label' => 'Rever o professor confirmado', 'route' => ['planning.index', []]];
        } else {
            $actions[] = ['label' => 'Habilitar outro professor', 'route' => ['admin.professors.index', []]];
        }
        $actions[] = ['label' => 'Rever a carga na montagem', 'route' => $this->montagem($o)];

        $this->add('blocker', 'offering_capacity', "{$label}: faltam horários disponíveis", $detail, [$o], $actions);
    }

    private function flushOrphans(): void
    {
        foreach ($this->orphans as $list) {
            $classes = array_column($list, 'class');
            sort($classes);
            $this->add('blocker', 'no_candidate', $list[0]['subject'] . ': nenhum professor habilitado',
                'Ninguém marcou esta disciplina na habilitação docente, então ' . (count($list) > 1 ? 'as turmas ' . implode(', ', $classes) . ' ficam' : 'a turma ' . $classes[0] . ' fica')
                . ' sem professor e o resolvedor não tem a quem atribuí-la. Peça a um professor que marque a disciplina em "Minhas disciplinas" ou habilite alguém pela coordenação.',
                $list, [
                    ['label' => 'Habilitar um professor', 'route' => ['admin.professors.index', []]],
                    ['label' => 'Atribuir na montagem', 'route' => $this->montagem($list[0])],
                ]);
        }
    }

    /** Professor que obrigatoriamente dá várias disciplinas (confirmado ou único habilitado). */
    private function checkProfessors($variable): void
    {
        $mandatory = [];
        foreach ($variable as $o) {
            $only = $o['fixedProfessor'] ?: (count($o['candidates']) === 1 ? $o['candidates'][0] : null);
            if ($only) {
                $mandatory[$only][] = $o;
            }
        }

        foreach ($mandatory as $professorId => $list) {
            $name = $this->professorName($professorId);
            $load = $this->professorNeed(collect($list));
            $byShift = collect($list)->groupBy('shift');

            // Por turno: aulas obrigatórias × horários livres na grade do professor.
            foreach ($byShift as $shift => $items) {
                $need = $this->professorNeed($items);
                $joint = $items->sum('expected') - $need;
                $free = array_values(array_filter($this->shiftCells($shift), fn ($cell) => $this->isAvailable($professorId, $cell)));
                if ($need > count($free) && $items->count() > 1) {
                    $alternatives = $items->filter(fn ($o) => count($o['candidates']) > 1)
                        ->map(fn ($o) => $this->offeringLabel($o) . ' (também: ' . implode(', ', array_map(fn ($id) => $this->professorName($id), array_diff($o['candidates'], [$professorId]))) . ')')
                        ->values()->all();
                    $detail = "{$name} precisa dar " . $this->plural($need, 'aula', 'aulas') . ' na ' . $this->shiftLabel($shift) . ' (' . $items->map(fn ($o) => $this->offeringLabel($o) . ' ×' . $o['expected'])->implode('; ')
                        . ')' . ($joint > 0 ? ", já descontadas {$this->plural($joint, 'aula conjunta', 'aulas conjuntas')}" : '')
                        . (($this->professors[$professorId]['hasGrid'] ?? false)
                            ? ', mas está disponível em só ' . $this->plural(count($free), 'horário', 'horários') . '.'
                            : ', e a ' . $this->shiftLabel($shift) . ' inteira tem só ' . count($free) . ' horários.');
                    $detail .= $alternatives ? ' Dá para redistribuir: ' . implode('; ', $alternatives) . '.' : ' Nenhuma dessas disciplinas tem outro professor habilitado.';
                    $this->add('blocker', 'professor_overload', "{$name}: mais aulas do que horários disponíveis", $detail, $items->all(), [
                        ['label' => "Disponibilidade de {$name}", 'route' => ['admin.professors.availability', [$professorId]]],
                        ['label' => 'Redistribuir professores por turma', 'route' => ['planning.index', []]],
                    ]);
                }
                elseif ($items->count() > 1 && $need >= 0.9 * count($free)) {
                    $this->add('risk', 'professor_tight', "{$name}: quase sem folga na " . $this->shiftLabel($shift),
                        "{$name} precisa de {$need} dos " . count($free) . ' horários em que pode dar aula na ' . $this->shiftLabel($shift)
                        . ' (' . $items->count() . ' disciplinas' . ($joint > 0 ? ", já descontadas {$this->plural($joint, 'aula conjunta', 'aulas conjuntas')}" : '')
                        . '). Qualquer restrição a mais (sala, limite diário, turma) pode impedir a grade de fechar.',
                        $items->all(), [
                            ['label' => "Disponibilidade de {$name}", 'route' => ['admin.professors.availability', [$professorId]]],
                            ['label' => 'Redistribuir professores por turma', 'route' => ['planning.index', []]],
                        ]);
                }
            }

            // Limites definidos pela coordenação (restrição flexível, por isso "risco").
            $p = $this->professors[$professorId] ?? null;
            $cap = null;
            if ($p && $p['maxPerDay'] && $p['maxDays']) {
                $cap = $p['maxPerDay'] * $p['maxDays'];
            } elseif ($p && $p['maxPerDay']) {
                $cap = $p['maxPerDay'] * 6;
            }
            if ($cap !== null && $load > $cap) {
                $this->add('risk', 'professor_limits', "{$name}: carga acima dos limites definidos",
                    "Tem {$this->plural($load, 'aula obrigatória', 'aulas obrigatórias')} na semana, mas os limites ({$p['maxPerDay']} por dia"
                    . ($p['maxDays'] ? " em até {$p['maxDays']} dias" : '') . ") permitem no máximo {$cap}. A grade vai fechar acima do limite.",
                    $list, [['label' => 'Ajustar limites do professor', 'route' => ['admin.professors.edit', [$professorId]]]]);
            }
        }
    }

    /** Turma: cabe no turno? Os professores das disciplinas cobrem horários suficientes juntos? */
    private function checkClasses($offerings): void
    {
        foreach ($offerings->groupBy(fn ($o) => $o['class'] . '|' . $o['shift']) as $items) {
            $first = $items->first();
            $cells = $this->shiftCells($first['shift']);
            // Estudos dirigidos podem se sobrepor entre si: contam como uma única ocupação.
            $regular = $items->where('directed', false);
            $need = $regular->sum('expected') + ($items->where('directed', true)->max('expected') ?? 0);
            $label = "Turma {$first['class']} ({$this->shiftLabel($first['shift'])})";

            if ($need > count($cells)) {
                $this->add('blocker', 'class_overload', "{$label}: mais aulas do que horários no turno",
                    "A turma soma {$this->plural($need, 'aula', 'aulas')} por semana, mas a {$this->shiftLabel($first['shift'])} tem "
                    . count($cells) . ' horários (6 dias × ' . count($this->problem['shifts'][$first['shift']] ?? []) . ' blocos).',
                    $items->all(), [['label' => 'Rever a carga das disciplinas', 'route' => $this->montagem($first)]]);

                continue;
            }

            // Horários em que pelo menos uma disciplina da turma tem professor disponível.
            $regular = $regular->filter(fn ($o) => ($o['fixedProfessor'] ? [$o['fixedProfessor']] : $o['candidates']) !== []);
            $usable = array_filter($cells, function ($cell) use ($regular) {
                foreach ($regular as $o) {
                    $candidates = $o['frozen'] ? $o['candidates'] : ($o['fixedProfessor'] ? [$o['fixedProfessor']] : $o['candidates']);
                    if ($candidates !== [] && $this->anyAvailable($candidates, $cell)) {
                        return true;
                    }
                }

                return false;
            });
            $regularNeed = $regular->sum('expected');
            if ($regularNeed > count($usable) && $regular->count() > 1) {
                $empty = array_values(array_diff($cells, $usable));
                $this->add('blocker', 'class_coverage', "{$label}: professores disponíveis em poucos horários",
                    "As disciplinas somam {$this->plural($regularNeed, 'aula', 'aulas')}, mas só em " . count($usable)
                    . ' horários há algum professor delas disponível. Ninguém está livre em: ' . $this->cellList($empty, 10) . '.',
                    $regular->all(), [['label' => 'Ver a turma na grade global', 'route' => ['allocation.board', []]]]);
            }
        }
    }

    /** Tipo de sala exigido (laboratórios): aulas no turno × salas × horários. */
    private function checkRooms($variable): void
    {
        $totalRooms = Room::where('active', true)->count();
        // Só interessa quem exige um tipo específico (subconjunto das salas).
        $groups = $variable->filter(fn ($o) => $o['rooms'] !== [] && count($o['rooms']) < $totalRooms)
            ->groupBy(fn ($o) => $o['shift'] . '|' . implode(',', $o['rooms']));

        foreach ($groups as $items) {
            $first = $items->first();
            $capacity = count($first['rooms']) * count($this->shiftCells($first['shift']));
            $need = $items->sum('expected');
            if ($need > $capacity) {
                $this->add('blocker', 'room_capacity', 'Salas ' . implode(', ', $first['rooms']) . ': ocupação acima da capacidade',
                    "Na {$this->shiftLabel($first['shift'])}, " . $items->count() . " disciplinas exigem essas salas e somam {$this->plural($need, 'aula', 'aulas')}, mas elas comportam {$capacity}.",
                    $items->all(), [['label' => 'Rever o tipo de sala na matriz', 'route' => ['admin.matrices.index', []]]]);
            } elseif ($need > $capacity * 0.85) {
                $this->add('risk', 'room_pressure', 'Salas ' . implode(', ', $first['rooms']) . ': quase lotadas',
                    "Na {$this->shiftLabel($first['shift'])}, as disciplinas que exigem essas salas ocupam {$need} de {$capacity} horários. Pouca folga para evitar choques.",
                    $items->all(), []);
            }
        }
    }

    private function checkMissingGrids($variable): void
    {
        $ids = $variable->flatMap(fn ($o) => $o['fixedProfessor'] ? [$o['fixedProfessor']] : $o['candidates'])->unique();
        $without = $ids->filter(fn ($id) => ! ($this->professors[$id]['hasGrid'] ?? false))->values();
        if ($without->isEmpty()) {
            return;
        }
        $names = $without->map(fn ($id) => $this->professorName($id))->sort()->values();
        $this->add('info', 'no_grid', $this->plural($without->count(), 'professor sem', 'professores sem') . ' grade de disponibilidade',
            'Sem a grade CCG-FOR-02, o resolvedor supõe disponibilidade total e o diagnóstico não enxerga restrições reais: '
            . $names->take(12)->implode(', ') . ($names->count() > 12 ? ' e mais ' . ($names->count() - 12) : '') . '.',
            [], array_map(fn ($id) => ['label' => 'Grade de ' . $this->professorName($id), 'route' => ['admin.professors.availability', [$id]]], $without->take(4)->all()),
            $without->count());
    }

    /**
     * Mapa de disputa por horário: em cada dia × bloco de cada turno, quantos professores
     * que dão aula naquele turno estão livres, contra quantas turmas o turno tem.
     * Se há menos professores livres do que turmas, aquele horário é disputado.
     *
     * @return array<string, array{letters: array<string, string>, turmas: int, professors: int, no_grid: int, cells: array<string, array>}>
     */
    private function contention($variable): array
    {
        $map = [];
        foreach ($variable->groupBy('shift') as $shift => $items) {
            $turmas = $items->pluck('class')->unique()->count();
            $ids = $items->flatMap(fn ($o) => $o['fixedProfessor'] ? [$o['fixedProfessor']] : $o['candidates'])->unique()->values();
            $withGrid = $ids->filter(fn ($id) => $this->professors[$id]['hasGrid'] ?? false);
            $noGrid = $ids->count() - $withGrid->count();

            $cells = [];
            foreach ($this->shiftCells($shift) as $cell) {
                $free = $withGrid->filter(fn ($id) => $this->isAvailable($id, $cell));
                $preferred = $free->filter(fn ($id) => ($this->professors[$id]['availability'][$cell] ?? null) === 'preferred')->count();
                // Sem grade conta como livre (o resolvedor também supõe), mas aparece separado.
                $supply = $free->count() + $noGrid;
                $cells[$cell] = [
                    'free' => $free->count(),
                    'preferred' => $preferred,
                    'supply' => $supply,
                    'level' => $supply === 0 ? 'none' : ($supply < $turmas ? 'short' : ($supply < 1.5 * $turmas ? 'tight' : 'ok')),
                    'names' => $free->map(fn ($id) => $this->professorName($id))->sort()->values()->all(),
                ];
            }

            $starts = $this->problem['shifts'][$shift] ?? [];
            $map[$shift] = [
                'label' => $this->shiftLabel($shift),
                'letters' => $starts,
                'turmas' => $turmas,
                'professors' => $ids->count(),
                'no_grid' => $noGrid,
                'cells' => $cells,
            ];
        }
        uksort($map, fn ($a, $b) => array_search($a, ['MANHÃ', 'TARDE', 'NOITE', 'INTEGRAL']) <=> array_search($b, ['MANHÃ', 'TARDE', 'NOITE', 'INTEGRAL']));

        return $map;
    }

    // ------------------------------------------------------------------ apoio

    /**
     * Horários que o professor realmente precisa: aula conjunta (mesma disciplina em
     * turmas diferentes) e estudos dirigidos podem dividir o mesmo horário.
     */
    private function professorNeed($items): int
    {
        return (int) $items->groupBy(fn ($o) => $o['directed'] ? 'ED' : $o['subjectKey'])->sum(fn ($group) => $group->max('expected'));
    }

    private function add(string $level, string $kind, string $title, string $detail, array $offerings, array $actions, ?int $classes = null): void
    {
        $this->issues[] = [
            'level' => $level,
            'kind' => $kind,
            'title' => $title,
            'detail' => $detail,
            'classes' => $classes ?? array_sum(array_column($offerings, 'expected')),
            'offerings' => array_map(fn ($o) => [
                'id' => $o['id'], 'label' => $this->offeringLabel($o), 'expected' => $o['expected'],
                'placed' => array_sum(array_map('count', $o['placements'])),
                'professor' => $o['professor'] ? $this->professorName($o['professor']) : null,
            ], $offerings),
            'actions' => array_values(array_map(fn ($a) => ['label' => $a['label'], 'url' => route($a['route'][0], $a['route'][1])], $actions)),
        ];
    }

    /** @return array<int, string> "dia|HH:MM" de todos os blocos do turno */
    private function shiftCells(string $shift): array
    {
        $starts = $this->problem['shifts'][$shift] ?? [];
        $cells = [];
        foreach (array_keys(self::DAYS) as $day) {
            foreach ($starts as $start) {
                $cells[] = $day . '|' . $start;
            }
        }

        return $cells;
    }

    private function isAvailable(int $professorId, string $cell): bool
    {
        $p = $this->professors[$professorId] ?? null;
        if (! $p || ! $p['hasGrid']) {
            return true; // sem grade: o resolvedor também supõe disponível
        }
        $pref = $p['availability'][$cell] ?? 'unavailable';

        return $pref === 'preferred' || $pref === 'available';
    }

    private function anyAvailable(array $professorIds, string $cell): bool
    {
        foreach ($professorIds as $id) {
            if ($this->isAvailable($id, $cell)) {
                return true;
            }
        }

        return false;
    }

    /** "Seg A, Ter B" a partir de "1|18:30". */
    private function cellList(array $cells, int $limit = 8): string
    {
        $letters = [];
        foreach ($this->problem['shifts'] as $starts) {
            foreach ($starts as $letter => $start) {
                $letters[$start] = $letter;
            }
        }
        $labels = array_map(function ($cell) use ($letters) {
            [$day, $start] = explode('|', $cell);

            return self::DAYS[(int) $day] . ' ' . ($letters[$start] ?? $start);
        }, $cells);

        return implode(', ', array_slice($labels, 0, $limit)) . (count($labels) > $limit ? ' e mais ' . (count($labels) - $limit) : '');
    }

    private function offeringLabel(array $o): string
    {
        return $o['subject'] . ' (' . $o['class'] . ')';
    }

    private function professorName(int $id): string
    {
        return $this->professors[$id]['name'] ?? "Professor #{$id}";
    }

    private function shiftLabel(string $shift): string
    {
        return self::SHIFT_LABELS[$shift] ?? mb_strtolower($shift);
    }

    private function plural(int $n, string $one, string $many): string
    {
        return $n . ' ' . ($n === 1 ? $one : $many);
    }

    /** Montagem por turma já filtrada no curso/turno da oferta. */
    private function montagem(array $o): array
    {
        return ['allocation.index', array_filter(['shift' => $o['shift'], 'course' => $o['course'] ?? null])];
    }
}
