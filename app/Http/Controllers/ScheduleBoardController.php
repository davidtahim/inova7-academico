<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Services\AvailabilityGrid;
use App\Services\ScheduleAllocator;
use App\Services\ScheduleScorer;
use App\Services\TimeBlocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Grade global (RF04): todas as turmas de um curso/turno lado a lado, com arrastar e soltar,
 * pontuação ao vivo (RN02) e alertas por horário.
 */
class ScheduleBoardController extends Controller
{
    public function index()
    {
        $term = AvailabilityGrid::selectedTerm();

        return view('allocation.board', [
            'term' => $term,
            'courses' => \App\Support\CourseScope::filter(Course::where('active', true), 'id')->whereHas('offerings', fn ($q) => $q->where('academic_term_id', $term?->id))->orderBy('name')->get(),
            'weights' => ScheduleScorer::WEIGHTS,
        ]);
    }

    public function data(Request $request, ScheduleScorer $scorer): JsonResponse
    {
        $term = AvailabilityGrid::selectedTerm();
        abort_unless($term, 404);

        return response()->json($this->payload($term, $request->integer('course') ?: null, $request->string('shift')->toString() ?: 'NOITE', $scorer));
    }

    /**
     * Move uma aula: de (dia, letra) para (dia, letra). Sem "from" = colocar uma aula que falta;
     * sem "to" = tirar a aula da grade. O movimento passa pelo validador: erro recusa, aviso aplica.
     */
    public function move(Request $request, ScheduleAllocator $allocator, ScheduleScorer $scorer): JsonResponse
    {
        $validated = $request->validate([
            'offering_id' => ['required', 'integer', 'exists:class_offerings,id'],
            'from' => ['nullable', 'array'],
            'from.weekday' => ['required_with:from', 'integer', 'between:1,6'],
            'from.letter' => ['required_with:from', Rule::in(['A', 'B', 'C', 'D', 'E', 'F'])],
            'to' => ['nullable', 'array'],
            'to.weekday' => ['required_with:to', 'integer', 'between:1,6'],
            'to.letter' => ['required_with:to', Rule::in(['A', 'B', 'C', 'D', 'E', 'F'])],
            'course' => ['nullable', 'integer'],
        ]);

        $offering = ClassOffering::with(['slots', 'assignments', 'subject', 'matrixDiscipline'])->findOrFail($validated['offering_id']);
        $current = $allocator->currentInput($offering);
        $placements = $current['placements'];

        if ($from = $validated['from'] ?? null) {
            $placements[$from['weekday']] = array_values(array_diff($placements[$from['weekday']] ?? [], [$from['letter']]));
        }
        if ($to = $validated['to'] ?? null) {
            if (in_array($to['letter'], $placements[$to['weekday']] ?? [], true)) {
                return response()->json(['saved' => false, 'errors' => ['Essa aula já está nesse horário.'], 'warnings' => []], 422);
            }
            $placements[$to['weekday']][] = $to['letter'];
        }

        $result = $allocator->apply($offering, [
            'professor_id' => $current['professor_id'],
            'placements' => array_filter($placements),
            'room' => $current['room'],
        ]);

        $term = AcademicTerm::find($offering->academic_term_id);

        return response()->json($result + [
            'board' => $this->payload($term, $validated['course'] ?? $offering->course_id, $offering->shift, $scorer),
        ], $result['saved'] ? 200 : 422);
    }

    private function payload(AcademicTerm $term, ?int $courseId, string $shift, ScheduleScorer $scorer): array
    {
        $offerings = ClassOffering::with(['subject', 'slots', 'assignments.professor', 'matrixDiscipline'])
            ->where('academic_term_id', $term->id)
            ->where('occurs', true)
            ->where('shift', $shift)
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId), fn ($q) => \App\Support\CourseScope::filter($q))
            ->get();

        $classes = $offerings->groupBy('class_code')->sortKeys()->map(fn ($group, $code) => [
            'code' => $code,
            'period' => (int) $group->first()->period,
            'offerings' => $group->sortBy(fn ($o) => $o->subject->name)->values()->map(function (ClassOffering $o) {
                $professor = $o->assignments->first()?->professor;
                $placements = [];
                foreach ($o->slots as $slot) {
                    foreach (str_split(TimeBlocks::lettersFor($slot->starts_at, $slot->ends_at, $o->shift) ?: '') as $letter) {
                        if ($letter !== '') {
                            $placements[] = ['weekday' => (int) $slot->weekday, 'letter' => $letter, 'room' => $slot->room];
                        }
                    }
                }

                return [
                    'id' => $o->id,
                    'subject' => $o->subject->name,
                    'abbr' => $o->subject->short_label,
                    'professor_id' => $professor?->id,
                    'professor' => $professor?->name,
                    'color' => $professor ? self::color($professor->id) : '#e9ecef',
                    'expected' => $o->classesToSchedule(),
                    'distance' => $o->isDistance(),
                    'directed_study' => (bool) $o->directed_study,
                    'placements' => $placements,
                ];
            })->all(),
        ])->values();

        $score = $scorer->score($term);
        $letters = TimeBlocks::forShift($shift);
        $rowIssues = [];
        foreach (TimeBlocks::SHORT_WEEKDAYS as $weekday => $label) {
            foreach ($letters as $letter => $slot) {
                $rowIssues[$weekday . $letter] = $score['cells'][$weekday . '|' . $slot['start']] ?? 0;
            }
        }

        return [
            'term' => ['code' => $term->code, 'published' => $term->isSchedulePublished()],
            'shift' => $shift,
            'letters' => $letters,
            'weekdays' => TimeBlocks::SHORT_WEEKDAYS,
            'classes' => $classes,
            'score' => $score,
            'row_issues' => $rowIssues,
        ];
    }

    /** Cor estável por professor (RNF01), em tons claros legíveis com texto escuro. */
    public static function color(int $professorId): string
    {
        $hue = ($professorId * 137) % 360;

        return "hsl({$hue}, 70%, 86%)";
    }
}
