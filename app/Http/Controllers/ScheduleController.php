<?php

namespace App\Http\Controllers;

use App\Models\ClassOffering;
use App\Models\Course;
use App\Services\AvailabilityGrid;
use App\Services\EnrollmentPlanner;
use App\Services\TimeBlocks;
use Illuminate\Http\Request;

/**
 * Horários das turmas (consulta para alunos e demais perfis): grade semanal por turma,
 * com disciplina, professor e sala.
 */
class ScheduleController extends Controller
{
    public function index(Request $request, EnrollmentPlanner $planner)
    {
        $term = AvailabilityGrid::selectedTerm();
        $user = $request->user();
        $myOfferings = $term && $user->isStudent() ? $planner->enrolledOfferings($user, $term) : collect();
        $courses = Course::where('active', true)
            ->when($term, fn ($q) => $q->whereHas('offerings', fn ($o) => $o->where('academic_term_id', $term->id)))
            ->orderBy('name')->get();
        $courseId = $request->integer('course') ?: $courses->first()?->id;

        $offerings = $term && $courseId
            ? ClassOffering::with(['subject', 'slots', 'assignments.professor'])
                ->where('academic_term_id', $term->id)
                ->where('course_id', $courseId)
                ->where('occurs', true)
                ->get()
            : collect();

        $classCodes = $offerings->sortBy(fn ($o) => [$o->period, $o->class_code])->pluck('class_code')->unique()->values();
        $classCode = $request->string('turma')->toString();
        if (! $classCodes->contains($classCode)) {
            $classCode = $classCodes->first();
        }

        $selected = $offerings->where('class_code', $classCode)->sortBy(fn ($o) => $o->subject->name)->values();
        $shift = $selected->first()?->shift ?? 'NOITE';

        return view('schedules.index', [
            'term' => $term,
            'courses' => $courses,
            'courseId' => $courseId,
            'classOptions' => $classCodes->map(fn ($code) => [
                'code' => $code,
                'label' => $code . ' — ' . $offerings->firstWhere('class_code', $code)->period . 'º período, ' . $offerings->firstWhere('class_code', $code)->shift,
            ]),
            'classCode' => $classCode,
            'shift' => $shift,
            'offerings' => $selected,
            'entries' => $this->entries($selected),
            'letters' => TimeBlocks::forShift($shift),
            'weekdays' => TimeBlocks::SHORT_WEEKDAYS,
            'myOfferings' => $myOfferings->sortBy(fn ($o) => $o->subject->name)->values(),
            'myGrids' => $myOfferings->groupBy('shift')->map(fn ($group, $shift) => [
                'letters' => TimeBlocks::forShift($shift),
                'entries' => self::entries($group),
            ]),
        ]);
    }

    /** Itens da grade: [weekday][letter] => [{subject, professor, room}] */
    public static function entries($offerings): array
    {
        $grid = [];
        foreach ($offerings as $offering) {
            foreach ($offering->slots as $slot) {
                foreach (str_split(TimeBlocks::lettersFor($slot->starts_at, $slot->ends_at, $offering->shift) ?: '') as $letter) {
                    if ($letter === '') {
                        continue;
                    }
                    $grid[(int) $slot->weekday][$letter][] = [
                        'offering_id' => $offering->id,
                        'subject' => $offering->subject->name,
                        'professor' => $offering->assignments->first()?->professor?->name,
                        'room' => $slot->room,
                    ];
                }
            }
        }

        return $grid;
    }
}
