<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\Professor;
use App\Models\Room;
use App\Services\AvailabilityGrid;
use App\Services\ConflictDetector;
use App\Services\ScheduleAllocator;
use App\Services\TimeBlocks;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Montagem de horários (coordenação): para cada disciplina da turma, escolhe professor,
 * alunos/Estudo Dirigido/hora-aula, dias, blocos (AB/CD) e sala — com validação de choques.
 */
class AllocationController extends Controller
{
    public function index(Request $request, ScheduleAllocator $allocator, ConflictDetector $detector)
    {
        $term = AvailabilityGrid::selectedTerm();
        $courses = Course::where('active', true)->orderBy('name')->get();
        $courseId = $request->integer('course') ?: $courses->first()?->id;

        $offerings = $term
            ? ClassOffering::with(['subject', 'course', 'slots', 'assignments.professor', 'matrixDiscipline'])
                ->where('academic_term_id', $term->id)
                ->where('occurs', true)
                ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
                ->when($request->filled('shift'), fn ($q) => $q->where('shift', $request->string('shift')))
                ->when($request->filled('period'), fn ($q) => $q->where('period', $request->integer('period')))
                ->orderBy('period')->orderBy('class_code')
                ->get()
                ->sortBy(fn ($o) => [$o->period, $o->class_code, $o->subject->name])
            : collect();

        $classes = $offerings->groupBy('class_code');
        $periods = $term
            ? ClassOffering::where('academic_term_id', $term->id)->when($courseId, fn ($q) => $q->where('course_id', $courseId))->distinct()->orderBy('period')->pluck('period')
            : collect();

        $qualified = Professor::with('subjects:id')->get()->mapWithKeys(fn ($p) => [$p->id => $p->subjects->pluck('id')->all()]);

        // Para a mini grade do modal: onde cada professor já dá aula no semestre ("dia|HH:MM")
        // e a grade de disponibilidade dele (preferred | available | unavailable).
        $busy = [];
        if ($term) {
            $allSlots = \App\Models\ScheduleSlot::with('offering.subject')->whereNotNull('professor_id')
                ->whereHas('offering', fn ($q) => $q->where('academic_term_id', $term->id))->get();
            foreach ($allSlots as $slot) {
                $o = $slot->offering;
                foreach (str_split(TimeBlocks::lettersFor($slot->starts_at, $slot->ends_at, $o->shift) ?: '') as $letter) {
                    $start = TimeBlocks::forShift($o->shift)[$letter]['start'] ?? null;
                    if ($start) {
                        $busy[$slot->professor_id][$slot->weekday . '|' . $start][] = ['offering' => $o->id, 'label' => ($o->subject?->short_label ?? '') . ' · ' . $o->class_code];
                    }
                }
            }
        }
        $availability = $term ? \App\Models\ProfessorAvailability::where('academic_term_id', $term->id)->get()
            ->groupBy('professor_id')->map(fn ($rows) => $rows->mapWithKeys(fn ($a) => [$a->weekday . '|' . substr((string) $a->starts_at, 0, 5) => $a->preference]))->all() : [];

        return view('allocation.index', [
            'term' => $term,
            'courses' => $courses,
            'courseId' => $courseId,
            'periods' => $periods,
            'classes' => $classes,
            'inputs' => $offerings->mapWithKeys(fn ($o) => [$o->id => $allocator->currentInput($o)]),
            'professors' => Professor::where('active', true)->orderBy('name')->get(),
            'qualified' => $qualified,
            'rooms' => Room::where('active', true)->orderBy('code')->get(),
            'weekdays' => TimeBlocks::SHORT_WEEKDAYS,
            'conflicts' => $term ? $detector->forTerm($term->id) : collect(),
            'sourceTerms' => $term ? AcademicTerm::whereKeyNot($term->id)->whereHas('offerings.slots')->orderByDesc('code')->get() : collect(),
            'directedStudyMax' => ScheduleAllocator::DIRECTED_STUDY_MAX_STUDENTS,
            'professorBusy' => $busy,
            'professorAvailability' => $availability,
        ]);
    }

    public function update(Request $request, ClassOffering $offering, ScheduleAllocator $allocator)
    {
        $validated = $request->validate([
            'professor_id' => ['nullable', 'integer', 'exists:professors,id'],
            'student_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'directed_study' => ['nullable', 'boolean'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,6'],
            'letters' => ['nullable', 'array'],
            'letters.*' => ['string', Rule::in(['A', 'B', 'C', 'D', 'E', 'F'])],
            'room' => ['nullable', 'string', 'exists:rooms,code'],
            // Modal: horário por dia (Seg AB, Qua CD…). Sem ele, vale o formato antigo dias + blocos.
            'placements' => ['nullable', 'array'],
            'placements.*' => ['array'],
            'placements.*.*' => ['string', Rule::in(['A', 'B', 'C', 'D', 'E', 'F'])],
        ]);

        $schedule = $request->boolean('per_day')
            ? ['placements' => collect($validated['placements'] ?? [])->filter(fn ($l, $d) => (int) $d >= 1 && (int) $d <= 6)->map(fn ($l) => array_values(array_unique($l)))->all()]
            : ['weekdays' => $validated['weekdays'] ?? [], 'letters' => $validated['letters'] ?? []];

        $input = $schedule + [
            'professor_id' => $validated['professor_id'] ?? null,
            'room' => $validated['room'] ?? null,
            'student_count' => $validated['student_count'] ?? null,
            'directed_study' => $request->boolean('directed_study'),
            'weekly_hours' => $validated['weekly_hours'] ?? $offering->weekly_hours,
        ];

        $result = $allocator->apply($offering, $input);

        $redirect = redirect()->to(url()->previous() . '#oferta-' . $offering->id);
        if (! $result['saved']) {
            return $redirect->withInput()->with('allocation', [
                'id' => $offering->id, 'errors' => $result['errors'], 'warnings' => $result['warnings'],
            ]);
        }

        return $redirect->with('allocation', [
            'id' => $offering->id, 'saved' => true, 'warnings' => $result['warnings'],
        ]);
    }

    /** RF07: torna a grade do semestre visível para alunos e professores. */
    public function publish(Request $request, ConflictDetector $detector)
    {
        $term = AvailabilityGrid::selectedTerm();
        abort_unless($term, 404);

        $term->update(['schedule_published_at' => now(), 'schedule_published_by' => $request->user()->id]);
        $conflicts = $detector->forTerm($term->id)->count();

        return back()->with('success', "Grade {$term->code} publicada." . ($conflicts ? " Atenção: {$conflicts} choque(s) ainda pendente(s)." : ''));
    }

    public function unpublish()
    {
        $term = AvailabilityGrid::selectedTerm();
        abort_unless($term, 404);
        $term->update(['schedule_published_at' => null, 'schedule_published_by' => null]);

        return back()->with('success', "Grade {$term->code} voltou para rascunho: alunos e professores deixam de vê-la.");
    }

    public function copy(Request $request, ScheduleAllocator $allocator)
    {
        $target = AvailabilityGrid::selectedTerm();
        abort_unless($target, 404);

        $validated = $request->validate([
            'source_term_id' => ['required', 'integer', 'exists:academic_terms,id', Rule::notIn([$target->id])],
        ]);

        $source = AcademicTerm::findOrFail($validated['source_term_id']);
        $report = $allocator->copyFromTerm($source, $target);

        return back()->with('copy_report', ['source' => $source->code, 'target' => $target->code] + $report);
    }
}
