<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use Illuminate\Support\Collection;

/**
 * Pontuação de penalidade da grade do semestre (RN02): quanto menor, melhor; zero é a grade ideal.
 * Restrições rígidas (RN01) valem 1000 cada; as flexíveis, de 1 a 20.
 */
class ScheduleScorer
{
    public const WEIGHTS = [
        'conflict' => 1000,        // choque de professor, sala ou turma
        'unavailable' => 1000,     // aula fora da disponibilidade do professor
        'missing' => 1000,         // aula que falta distribuir para cumprir a carga
        'excess' => 1000,          // aula além da carga
        'gap' => 5,                // janela vaga do professor dentro do turno
        'over_day' => 20,          // aula acima do máximo diário do professor
        'over_days' => 20,         // dia acima do máximo semanal do professor
        'concentrated' => 3,       // 3+ aulas da disciplina no mesmo dia
        'not_preferred' => 1,      // aula em horário "disponível" (não "preferido")
    ];

    public const LABELS = [
        'conflict' => 'Choques de professor, sala ou turma',
        'unavailable' => 'Aulas fora da disponibilidade',
        'missing' => 'Aulas faltando para a carga',
        'excess' => 'Aulas além da carga',
        'gap' => 'Janelas vagas de professores',
        'over_day' => 'Aulas acima do máximo diário',
        'over_days' => 'Dias acima do máximo semanal',
        'concentrated' => 'Disciplinas concentradas num só dia',
        'not_preferred' => 'Aulas fora do horário preferido',
    ];

    public function __construct(private ConflictDetector $detector)
    {
    }

    /**
     * @return array{total: int, hard: int, soft: int, breakdown: array<int, array>, cells: array<string, int>}
     *   cells: "dia|HH:MM" => nº de problemas naquele horário (indicador da coluna AULA).
     */
    public function score(AcademicTerm $term): array
    {
        $offerings = ClassOffering::with(['slots', 'matrixDiscipline', 'assignments'])
            ->where('academic_term_id', $term->id)->where('occurs', true)->get();

        $counts = array_fill_keys(array_keys(self::WEIGHTS), 0);
        $cells = [];
        $mark = function (int $weekday, string $start, int $n = 1) use (&$cells) {
            $key = $weekday . '|' . substr($start, 0, 5);
            $cells[$key] = ($cells[$key] ?? 0) + $n;
        };

        foreach ($this->detector->forTerm($term->id) as $conflict) {
            $counts['conflict']++;
            $slot = \App\Models\ScheduleSlot::find($conflict['left_id']);
            if ($slot) {
                $mark((int) $slot->weekday, $slot->starts_at);
            }
        }

        // [professor][dia] => letras ocupadas por turno (para janelas e limites)
        $professorDays = [];
        $availability = ProfessorAvailability::where('academic_term_id', $term->id)->get()->groupBy('professor_id');

        foreach ($offerings as $offering) {
            $placed = 0;
            $perDay = [];
            foreach ($offering->slots as $slot) {
                $letters = TimeBlocks::lettersFor($slot->starts_at, $slot->ends_at, $offering->shift);
                $placed += strlen($letters);
                $perDay[(int) $slot->weekday] = ($perDay[(int) $slot->weekday] ?? 0) + strlen($letters);

                $professorId = $slot->professor_id;
                if (! $professorId) {
                    continue;
                }
                $map = TimeBlocks::forShift($offering->shift);
                foreach (str_split($letters) as $letter) {
                    if ($letter === '') {
                        continue;
                    }
                    $professorDays[$professorId][(int) $slot->weekday][$offering->shift][] = $letter;

                    $grid = $availability->get($professorId);
                    if ($grid) {
                        $cell = $grid->first(fn ($a) => (int) $a->weekday === (int) $slot->weekday && substr((string) $a->starts_at, 0, 5) === $map[$letter]['start']);
                        if (! $cell || $cell->preference === 'unavailable') {
                            $counts['unavailable']++;
                            $mark((int) $slot->weekday, $map[$letter]['start']);
                        } elseif ($cell->preference === 'available') {
                            $counts['not_preferred']++;
                        }
                    }
                }
            }

            $expected = $offering->classesToSchedule();
            if ($expected > 0 && $placed < $expected) {
                $counts['missing'] += $expected - $placed;
            } elseif ($placed > $expected && ! $offering->isDistance()) {
                $counts['excess'] += $placed - $expected;
            }
            foreach ($perDay as $n) {
                if ($n >= 3) {
                    $counts['concentrated']++;
                }
            }
        }

        $professors = Professor::whereIn('id', array_keys($professorDays))->get()->keyBy('id');
        foreach ($professorDays as $professorId => $days) {
            $professor = $professors->get($professorId);
            foreach ($days as $byShift) {
                $dayTotal = 0;
                foreach ($byShift as $shift => $letters) {
                    $letters = array_unique($letters);
                    $dayTotal += count($letters);
                    $order = array_keys(TimeBlocks::forShift($shift));
                    $positions = array_map(fn ($l) => array_search($l, $order, true), $letters);
                    $counts['gap'] += (max($positions) - min($positions) + 1) - count($positions);
                }
                if ($professor?->max_classes_per_day && $dayTotal > $professor->max_classes_per_day) {
                    $counts['over_day'] += $dayTotal - $professor->max_classes_per_day;
                }
            }
            if ($professor?->max_days_per_week && count($days) > $professor->max_days_per_week) {
                $counts['over_days'] += count($days) - $professor->max_days_per_week;
            }
        }

        $breakdown = [];
        $hard = $soft = 0;
        foreach ($counts as $type => $count) {
            $points = $count * self::WEIGHTS[$type];
            self::WEIGHTS[$type] >= 1000 ? $hard += $count : $soft += $points;
            if ($count > 0) {
                $breakdown[] = ['type' => $type, 'label' => self::LABELS[$type], 'count' => $count, 'points' => $points, 'hard' => self::WEIGHTS[$type] >= 1000];
            }
        }

        return [
            'total' => array_sum(array_column($breakdown, 'points')),
            'hard' => $hard,
            'soft' => $soft,
            'breakdown' => $breakdown,
            'cells' => $cells,
        ];
    }
}
