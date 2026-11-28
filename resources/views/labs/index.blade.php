@extends('layouts.app')

@section('title', 'Reservas de laboratórios • Inova7 Acadêmico')
@section('page-title', 'Reservas de laboratórios')

@section('content')
    @php
        $weekLabel = $days->first()->format('d/m') . ' a ' . $days->last()->format('d/m/Y');
        $dayShort = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
    @endphp
    <div class="container-fluid py-3 px-0">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
            <div>
                <h2 class="h4 mb-1">Laboratórios — {{ \App\Support\LabAreas::label($area) }}{{ $term ? ' · ' . $term->code : '' }}</h2>
                <p class="text-muted small mb-0">
                    Aulas regulares entram sozinhas a partir da grade horária (Art. 16 I). Reservas avulsas, eventos e provas
                    são pedidas aqui e aprovadas pelo gestor dos laboratórios da área. Horários livres ficam para monitoria e estudo (Art. 16 II).
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reservationModal">{{ $manage ? 'Nova reserva' : 'Pedir reserva' }}</button>
                @if ($manage)
                    <form method="POST" action="{{ route('labs.sync') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary" title="Recria as reservas das aulas regulares a partir da grade do semestre">Sincronizar com a grade</button>
                    </form>
                    <a href="{{ route('labs.rooms', ['area' => $area]) }}" class="btn btn-outline-secondary">Cadastro de laboratórios</a>
                @endif
            </div>
        </div>

        {{-- Áreas: TI (NTI), Saúde, Comunicação, Engenharias, Arquitetura --}}
        <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Áreas de laboratórios">
            @foreach (\App\Support\LabAreas::options() as $key => $label)
                <a href="{{ route('labs.index', ['area' => $key]) }}" class="btn btn-sm {{ $area === $key ? 'btn-primary' : 'btn-outline-primary' }}" @if ($area === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($labs->isEmpty())
            <div class="alert alert-warning">Nenhum laboratório de {{ \App\Support\LabAreas::label($area) }} cadastrado.
                @if ($manage) <a href="{{ route('labs.rooms', ['area' => $area]) }}">Cadastre os laboratórios</a>. @endif
            </div>
        @else
            {{-- Pedidos pendentes: para o NTI decidir; para os demais, os próprios pedidos. --}}
            @if ($pending->isNotEmpty())
                <section class="card border-0 shadow-sm mb-3 border-start border-4 border-warning" aria-labelledby="pending-title">
                    <div class="card-body">
                        <h3 class="h6" id="pending-title">{{ $manage ? 'Pedidos aguardando sua aprovação' : 'Seus pedidos aguardando o gestor' }} ({{ $pending->count() }})</h3>
                        <ul class="list-unstyled mb-0 d-grid gap-2">
                            @foreach ($pending as $p)
                                <li class="d-flex flex-wrap align-items-center gap-2 small">
                                    <span class="badge bg-warning text-dark">{{ $p->tipo_solicitacao }}</span>
                                    <span class="me-auto">
                                        <strong>{{ $p->titulo }}</strong> · {{ $p->laboratorio?->code }} ·
                                        {{ $p->recorrente ? 'toda ' . $dayShort[$p->dia_semana] : $p->data?->format('d/m/Y') }} {{ $p->start() }}–{{ $p->end() }}
                                        @if ($p->participantes) · {{ $p->participantes }} pessoas @endif
                                        <span class="text-muted">· {{ $p->solicitante?->name }}</span>
                                    </span>
                                    @if ($manage)
                                        <form method="POST" action="{{ route('labs.reservations.approve', $p) }}" class="m-0">@csrf<button class="btn btn-sm btn-success">Aprovar</button></form>
                                        <form method="POST" action="{{ route('labs.reservations.reject', $p) }}" class="m-0 d-flex gap-1">@csrf
                                            <input type="text" name="motivo" class="form-control form-control-sm" placeholder="Motivo" style="width: 9rem">
                                            <button class="btn btn-sm btn-outline-danger">Recusar</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('labs.reservations.cancel', $p) }}" class="m-0">@csrf<button class="btn btn-sm btn-outline-secondary">Cancelar pedido</button></form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif

            @if ($theoryInLabs->isNotEmpty() && ($manage || Gate::allows('manage-planning')))
                <details class="alert alert-warning small mb-3">
                    <summary><strong>{{ $theoryInLabs->count() }} turma(s) ocupando laboratório sem exigi-lo</strong> (Art. 8º III: laboratório só para carga prática da área).
                        Informe a carga prática / o tipo de laboratório da disciplina na matriz, ou troque a sala na montagem.</summary>
                    <ul class="mb-0 mt-2">
                        @foreach ($theoryInLabs->take(30) as $r)
                            <li>{{ $r->turma->subject?->name }} ({{ $r->turma->class_code }}) em {{ $r->laboratorio?->code }}
                                @can('manage-catalog') · <a href="{{ route('admin.subjects.edit', $r->turma->subject_id) }}">carga prática</a> @endcan
                                @can('manage-planning') · <a href="{{ route('allocation.index', ['course' => $r->turma->course_id, 'shift' => $r->turma->shift]) }}">trocar sala</a> @endcan
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            {{-- Escolha do laboratório e da semana --}}
            <nav class="d-flex flex-wrap gap-2 mb-2" aria-label="Laboratórios">
                @foreach ($labs as $l)
                    <a href="{{ route('labs.index', ['area' => $area, 'lab' => $l->id, 'semana' => $monday->toDateString()]) }}"
                        class="btn btn-sm {{ $lab && $l->id === $lab->id ? 'btn-dark' : 'btn-outline-secondary' }} {{ $l->active ? '' : 'opacity-50' }}"
                        @if ($lab && $l->id === $lab->id) aria-current="page" @endif>
                        {{ $l->code }}{{ $l->computers ? ' · ' . $l->computers . ($l->isComputerLab() ? ' PCs' : ' vagas') : '' }}{{ $l->active ? '' : ' (inativo)' }}
                    </a>
                @endforeach
            </nav>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('labs.index', ['area' => $area, 'lab' => $lab->id, 'semana' => $monday->copy()->subWeek()->toDateString()]) }}" aria-label="Semana anterior">‹</a>
                <strong class="small">Semana de {{ $weekLabel }}</strong>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('labs.index', ['area' => $area, 'lab' => $lab->id, 'semana' => $monday->copy()->addWeek()->toDateString()]) }}" aria-label="Próxima semana">›</a>
                <a class="btn btn-sm btn-link" href="{{ route('labs.index', ['area' => $area, 'lab' => $lab->id]) }}">Hoje</a>
                <span class="ms-auto small text-muted">
                    {{ $stats['grade'] }} aulas regulares · {{ $stats['manual'] }} reservas aprovadas
                    @if ($lab->softwares) · <span title="{{ $lab->softwares }}">softwares: {{ \Illuminate\Support\Str::limit($lab->softwares, 60) }}</span> @endif
                </span>
            </div>

            <div class="d-flex flex-wrap gap-3 small mb-2" aria-hidden="true">
                <span><span class="lab-chip lab-grade">Aula regular</span> grade prática (Art. 16 I)</span>
                <span><span class="lab-chip lab-manual">Reserva</span> avulsa, evento ou prova</span>
                <span><span class="lab-chip lab-pending">Pendente</span> aguardando o gestor</span>
                <span><span class="lab-chip lab-free">Livre</span> monitoria e estudo (Art. 16 II)</span>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 lab-week">
                    <caption class="visually-hidden">Ocupação do laboratório {{ $lab->code }} na semana de {{ $weekLabel }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="lab-time">Horário</th>
                            @foreach ($days as $date)
                                <th scope="col" class="text-center {{ $date->isToday() ? 'lab-today' : '' }}">{{ $dayShort[$date->dayOfWeekIso] }} <span class="fw-normal text-muted">{{ $date->format('d/m') }}</span></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            @if ($i === 0 || $rows[$i - 1]['shift'] !== $row['shift'])
                                <tr class="lab-shift"><th colspan="7" scope="rowgroup">{{ $row['shift'] }}</th></tr>
                            @endif
                            <tr>
                                <th scope="row" class="lab-time"><strong>{{ $row['letter'] }}</strong> <span class="text-muted">{{ $row['start'] }}–{{ $row['end'] }}</span></th>
                                @foreach ($row['cells'] as $cell)
                                    @php $r = $cell['reservation'] ?? null; @endphp
                                    <td class="p-1">
                                        @switch($cell['kind'])
                                            @case('grade')
                                                @php $prof = $r->turma?->assignments->first()?->professor?->name; @endphp
                                                <div class="lab-chip lab-grade d-block" title="Aula regular · {{ $r->titulo }}{{ $prof ? ' · ' . $prof : '' }} · {{ $r->start() }}–{{ $r->end() }}">
                                                    <strong>{{ $r->turma?->subject?->short_label ?? 'Aula' }}</strong>
                                                    <small class="d-block text-truncate">{{ $r->turma?->class_code }}{{ $prof ? ' · ' . \Illuminate\Support\Str::of($prof)->explode(' ')->first() : '' }}</small>
                                                </div>
                                                @break
                                            @case('manual')
                                                <div class="lab-chip lab-manual d-block" title="{{ $r->tipo_solicitacao }} · {{ $r->titulo }} · {{ $r->solicitante?->name }}">
                                                    <strong>{{ $r->tipo_solicitacao }}</strong>
                                                    <small class="d-block text-truncate">{{ $r->titulo }}</small>
                                                    @if ($manage || (int) $r->solicitante_id === (int) auth()->id())
                                                        <form method="POST" action="{{ route('labs.reservations.cancel', $r) }}" class="m-0" onsubmit="return confirm('Cancelar esta reserva e liberar o horário?')">
                                                            @csrf<button class="btn btn-link btn-sm p-0 small">cancelar</button>
                                                        </form>
                                                    @endif
                                                </div>
                                                @break
                                            @case('pending')
                                                <div class="lab-chip lab-pending d-block" title="Pedido pendente · {{ $r->titulo }} · {{ $r->solicitante?->name }}">
                                                    <strong>Pendente</strong>
                                                    <small class="d-block text-truncate">{{ $r->titulo }}</small>
                                                </div>
                                                @break
                                            @case('free')
                                                <span class="lab-chip lab-free d-block text-center">Livre</span>
                                                @break
                                            @default
                                                <span class="d-block text-center text-muted small">fechado</span>
                                        @endswitch
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Pedido / nova reserva --}}
    <div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationTitle" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" action="{{ route('labs.reservations.store') }}">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="reservationTitle">{{ $manage ? 'Nova reserva' : 'Pedir reserva' }} — {{ \App\Support\LabAreas::label($area) }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="res-lab">Laboratório *</label>
                            <select id="res-lab" name="laboratorio_id" class="form-select" required>
                                @foreach ($labs->where('active', true) as $l)
                                    <option value="{{ $l->id }}" @selected((int) old('laboratorio_id', $lab?->id) === $l->id)>{{ $l->code }}{{ $l->computers ? ' · ' . $l->computers . ($l->isComputerLab() ? ' PCs' : ' vagas') : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="res-type">Tipo *</label>
                            <select id="res-type" name="tipo_solicitacao" class="form-select" required>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" @selected(old('tipo_solicitacao') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="res-title">Título *</label>
                            <input id="res-title" name="titulo" class="form-control" maxlength="150" required value="{{ old('titulo') }}" placeholder="Ex.: Prova de Banco de Dados — turma CSE0280103NNA">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="res-date">Data *</label>
                            <input id="res-date" type="date" name="data" class="form-control" required value="{{ old('data', now()->toDateString()) }}" min="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="res-start">Início *</label>
                            <input id="res-start" type="time" name="hora_inicio" class="form-control" required value="{{ old('hora_inicio', '18:30') }}" min="07:30" max="22:20">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="res-end">Término *</label>
                            <input id="res-end" type="time" name="hora_fim" class="form-control" required value="{{ old('hora_fim', '20:10') }}" min="07:30" max="22:20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="res-people">Participantes</label>
                            <input id="res-people" type="number" name="participantes" class="form-control" min="1" max="500" value="{{ old('participantes') }}">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="semanal" value="1" id="res-weekly" @checked(old('semanal'))>
                                <label class="form-check-label" for="res-weekly">Repetir toda semana até o fim do semestre</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="res-notes">Observações</label>
                            <textarea id="res-notes" name="observacoes" rows="2" class="form-control">{{ old('observacoes') }}</textarea>
                        </div>
                    </div>
                    <p class="small text-muted mt-3 mb-0">Funcionamento: seg–sex 07:30–22:20; sábado 07:30–12:00. O sistema recusa horários que chocam com aulas regulares ou outras reservas aprovadas.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">{{ $manage ? 'Reservar' : 'Enviar pedido' }}</button>
                </div>
            </form>
        </div>
    </div>
    @if ($errors->any() || session('error'))
        <script>window.addEventListener('load', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('reservationModal')).show());</script>
    @endif

    <style>
        .lab-week { table-layout: fixed; min-width: 760px; font-size: .8rem; }
        .lab-time { width: 8.5rem; white-space: nowrap; background: #fff; }
        .lab-shift th { background: #eef2f6 !important; font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #52606d; padding: .2rem .6rem; }
        .lab-today { background: #fff8e1 !important; }
        .lab-chip { display: inline-block; border-radius: .4rem; padding: .2rem .4rem; line-height: 1.2; font-size: .75rem; }
        .lab-grade { background: #dbe8f6; color: #0c3d67; border: 1px solid #b8cfe6; }
        .lab-manual { background: #fde2c4; color: #7a3d00; border: 1px solid #f5c48f; }
        .lab-pending { background: #fff; color: #7a5b00; border: 1px dashed #e0b400; }
        .lab-free { background: #eaf6ef; color: #146c43; border: 1px solid #cbe9d6; }
    </style>
@endsection
