<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\LabReservation;
use App\Models\Room;
use App\Services\GradeLaboratorioSyncService;
use App\Services\LabRules;
use App\Services\TimeBlocks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Controle de reservas dos laboratórios de informática (NTI).
 * Aulas regulares vêm da grade (GradeLaboratorioSyncService); aqui ficam os pedidos
 * avulsos, eventos, provas e monitorias, a aprovação do NTI e a grade de cada laboratório.
 */
class LabReservationController extends Controller
{
    public const MANUAL_TYPES = ['Avulsa' => 'Reserva avulsa', 'Evento' => 'Evento', 'Prova' => 'Prova', 'Monitoria' => 'Monitoria / estudo'];

    public function index(Request $request)
    {
        $labs = Room::where('type', LabRules::LAB)->orderByDesc('active')->orderBy('code')->get();
        $lab = $labs->firstWhere('id', $request->integer('lab')) ?? $labs->first();
        $term = AcademicTerm::current();

        // Semana exibida: a pedida; senão a atual (ou o início do semestre, se ainda não começou).
        $reference = $request->filled('semana') ? Carbon::parse($request->input('semana'))
            : (($term?->starts_at && now()->lt($term->starts_at)) ? $term->starts_at->copy() : now());
        $monday = $reference->copy()->startOfWeek(Carbon::MONDAY);
        $days = collect(range(0, 5))->map(fn ($i) => $monday->copy()->addDays($i));

        $reservations = $lab ? LabReservation::with(['turma.subject', 'turma.assignments.professor', 'solicitante'])
            ->where('laboratorio_id', $lab->id)->whereIn('status', ['Aprovada', 'Pendente'])
            ->where(fn ($q) => $q->where('recorrente', true)->orWhereBetween('data', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()]))
            ->get() : collect();

        $rows = [];
        foreach (['MANHÃ' => 'Manhã', 'TARDE' => 'Tarde', 'NOITE' => 'Noite'] as $shift => $label) {
            foreach (TimeBlocks::forShift($shift) as $letter => $slot) {
                $cells = [];
                foreach ($days as $date) {
                    $cells[] = $this->cell($date, $slot['start'], $slot['end'], $reservations);
                }
                $rows[] = ['shift' => $label, 'letter' => $letter, 'start' => $slot['start'], 'end' => $slot['end'], 'cells' => $cells];
            }
        }

        $manage = Gate::allows('manage-labs');
        $pending = LabReservation::with(['laboratorio', 'solicitante'])->where('status', 'Pendente')
            ->when(! $manage, fn ($q) => $q->where('solicitante_id', $request->user()->id))
            ->orderBy('data')->orderBy('hora_inicio')->get();

        // Aulas teóricas ocupando laboratório na grade (Art. 8º III): lista para corrigir.
        $theoryInLabs = LabReservation::with('turma.subject', 'turma.matrixDiscipline', 'laboratorio')
            ->where('origem', 'grade')->where('status', 'Aprovada')->when($term, fn ($q) => $q->where('academic_term_id', $term->id))
            ->get()->filter(fn ($r) => $r->turma && ! LabRules::offeringRequiresLab($r->turma))
            ->unique('turma_id')->values();

        return view('labs.index', [
            'theoryInLabs' => $theoryInLabs,
            'labs' => $labs, 'lab' => $lab, 'term' => $term, 'days' => $days, 'monday' => $monday, 'rows' => $rows,
            'pending' => $pending, 'manage' => $manage, 'types' => self::MANUAL_TYPES,
            'stats' => [
                'grade' => $reservations->where('origem', 'grade')->where('status', 'Aprovada')->count(),
                'manual' => $reservations->where('origem', 'manual')->where('status', 'Aprovada')->count(),
            ],
        ]);
    }

    /** O que ocupa o laboratório numa data e faixa: aula regular, reserva, pedido pendente, livre ou fechado. */
    private function cell(Carbon $date, string $start, string $end, $reservations): array
    {
        [$open, $close] = LabRules::OPENING[$date->dayOfWeekIso] ?? [null, null];
        if (! $open || $start < $open || $end > $close) {
            return ['kind' => 'closed'];
        }
        $hits = $reservations->filter(fn ($r) => $r->occursOn($date) && $r->start() < $end && $start < $r->end());
        $approved = $hits->firstWhere('status', 'Aprovada');
        if ($approved) {
            return ['kind' => $approved->isFromGrade() ? 'grade' : 'manual', 'reservation' => $approved, 'pending' => $hits->where('status', 'Pendente')->count()];
        }
        if ($pending = $hits->firstWhere('status', 'Pendente')) {
            return ['kind' => 'pending', 'reservation' => $pending];
        }

        return ['kind' => 'free'];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'laboratorio_id' => ['required', Rule::exists('rooms', 'id')->where('type', LabRules::LAB)],
            'tipo_solicitacao' => ['required', Rule::in(array_keys(self::MANUAL_TYPES))],
            'titulo' => ['required', 'string', 'max:150'],
            'data' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fim' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'participantes' => ['nullable', 'integer', 'min:1', 'max:500'],
            'semanal' => ['nullable', 'boolean'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ], [], ['hora_fim' => 'término', 'hora_inicio' => 'início', 'titulo' => 'título']);

        $lab = Room::findOrFail($data['laboratorio_id']);
        $date = Carbon::parse($data['data']);
        $term = AcademicTerm::current();
        $weekly = ! empty($data['semanal']);

        [$open, $close] = LabRules::OPENING[$date->dayOfWeekIso] ?? [null, null];
        if (! $open || $data['hora_inicio'] < $open || $data['hora_fim'] > $close) {
            return back()->withInput()->with('error', 'Fora do horário de funcionamento dos laboratórios (Art. 4º): seg–sex 07:30–22:20 e sábado 07:30–12:00.');
        }
        if (! $lab->active) {
            return back()->withInput()->with('error', "O laboratório {$lab->code} está inativo.");
        }

        $reservation = new LabReservation([
            'academic_term_id' => $term?->id, 'laboratorio_id' => $lab->id, 'solicitante_id' => $request->user()->id,
            'tipo_solicitacao' => $data['tipo_solicitacao'], 'origem' => 'manual', 'titulo' => $data['titulo'],
            'dia_semana' => $date->dayOfWeekIso, 'data' => $weekly ? null : $date->toDateString(),
            'hora_inicio' => $data['hora_inicio'], 'hora_fim' => $data['hora_fim'],
            'recorrente' => $weekly, 'frequencia' => $weekly ? 'Semanal' : null,
            'data_inicio' => $weekly ? $date->toDateString() : null, 'data_fim' => $weekly ? $term?->ends_at : null,
            'participantes' => $data['participantes'] ?? null, 'observacoes' => $data['observacoes'] ?? null,
        ]);

        if ($conflict = $this->conflict($reservation)) {
            return back()->withInput()->with('error', $conflict);
        }

        // O NTI cadastra já aprovada; os demais perfis pedem e aguardam aprovação.
        $approve = Gate::allows('manage-labs');
        $reservation->status = $approve ? 'Aprovada' : 'Pendente';
        $reservation->aprovado_por = $approve ? $request->user()->id : null;
        $reservation->save();

        $warning = $lab->computers && ($data['participantes'] ?? 0) > $lab->computers ? " Atenção: {$data['participantes']} participantes para {$lab->computers} computadores." : '';

        return redirect()->route('labs.index', ['lab' => $lab->id, 'semana' => $date->toDateString()])
            ->with('success', ($approve ? 'Reserva registrada e aprovada.' : 'Pedido enviado ao NTI: aguarda aprovação.') . $warning);
    }

    public function approve(Request $request, LabReservation $reservation)
    {
        if ($conflict = $this->conflict($reservation)) {
            return back()->with('error', 'Não aprovada: ' . $conflict);
        }
        $reservation->update(['status' => 'Aprovada', 'aprovado_por' => $request->user()->id]);

        return back()->with('success', 'Reserva aprovada.');
    }

    public function reject(Request $request, LabReservation $reservation)
    {
        $reservation->update(['status' => 'Recusada', 'aprovado_por' => $request->user()->id, 'observacoes' => trim(($reservation->observacoes ?? '') . "\nRecusada: " . $request->input('motivo', 'sem motivo informado'))]);

        return back()->with('success', 'Pedido recusado.');
    }

    public function cancel(Request $request, LabReservation $reservation)
    {
        abort_if($reservation->isFromGrade(), 403, 'Aula regular é cancelada pela grade horária (tire a aula ou troque a sala na montagem).');
        abort_unless(Gate::allows('manage-labs') || (int) $reservation->solicitante_id === (int) $request->user()->id, 403);
        $reservation->update(['status' => 'Cancelada']);

        return back()->with('success', 'Reserva cancelada: o horário ficou livre.');
    }

    public function sync(Request $request, GradeLaboratorioSyncService $sync)
    {
        $term = AcademicTerm::current();
        abort_unless($term, 404);
        $count = $sync->syncTerm($term, $request->user());

        return back()->with('success', "Reservas das aulas regulares de {$term->code} sincronizadas com a grade ({$count} turmas verificadas).");
    }

    /** Choque com aula regular ou reserva aprovada no mesmo laboratório e horário. */
    private function conflict(LabReservation $new): ?string
    {
        $others = LabReservation::approved()->where('laboratorio_id', $new->laboratorio_id)
            ->when($new->exists, fn ($q) => $q->whereKeyNot($new->id))->get()
            ->filter(fn ($r) => $r->start() < $new->end() && $new->start() < $r->end());

        foreach ($others as $other) {
            $clash = $new->recorrente
                ? ($other->recorrente ? (int) $other->dia_semana === (int) $new->dia_semana : $other->data && $new->occursOn($other->data))
                : $other->occursOn($new->data);
            if ($clash) {
                $what = $other->isFromGrade() ? 'aula regular de ' . $other->titulo : "{$other->tipo_solicitacao}: {$other->titulo}";

                return "Laboratório ocupado por {$what} ({$other->start()}–{$other->end()}).";
            }
        }

        return null;
    }

    // ---------------------------------------------------------------- cadastro dos laboratórios

    public function rooms()
    {
        return view('labs.rooms', ['labs' => Room::where('type', LabRules::LAB)->orderBy('code')->get()]);
    }

    public function storeRoom(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:rooms,code'],
            'name' => ['nullable', 'string', 'max:100'],
            'computers' => ['nullable', 'integer', 'min:0', 'max:500'],
            'softwares' => ['nullable', 'string', 'max:4000'],
        ]);
        Room::create($data + ['name' => $data['name'] ?? $data['code'], 'type' => LabRules::LAB, 'active' => true]);

        return back()->with('success', "Laboratório {$data['code']} cadastrado.");
    }

    public function updateRoom(Request $request, Room $room)
    {
        abort_unless($room->isComputerLab(), 404);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'computers' => ['nullable', 'integer', 'min:0', 'max:500'],
            'softwares' => ['nullable', 'string', 'max:4000'],
            'active' => ['nullable', 'boolean'],
        ]);
        $room->update(['name' => $data['name'] ?: $room->code, 'computers' => $data['computers'] ?? null, 'softwares' => $data['softwares'] ?? null, 'active' => (bool) ($data['active'] ?? false)]);

        return back()->with('success', "Laboratório {$room->code} atualizado.");
    }
}
