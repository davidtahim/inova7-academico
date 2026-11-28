@extends('layouts.app')

@section('title', 'Calendário dos laboratórios • Inova7')
@section('page-title', 'Calendário dos laboratórios')

@section('content')
    @php
        $dayShort = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
        $url = fn (array $params) => route('labs.public', array_filter($params + ['area' => $area]));
    @endphp
    <div class="lab-public {{ auth()->check() ? 'py-3' : 'container py-4' }}">
        <header class="d-flex flex-wrap align-items-center gap-3 mb-3">
            @guest
                <span class="brand-mark" aria-hidden="true">7</span>
            @endguest
            <div class="me-auto">
                <h1 class="h4 mb-0">Calendário dos laboratórios</h1>
                <p class="text-muted small mb-0">
                    Ocupação aprovada{{ $term ? ' · semestre ' . $term->code : '' }}. Horários <strong>livres</strong> podem ser usados para monitoria e estudo
                    (Art. 16 II); para reservar, fale com o gestor dos laboratórios da área.
                </p>
            </div>
            @guest
                <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">Entrar para pedir reserva</a>
            @else
                <a href="{{ route('labs.index', ['area' => $area]) }}" class="btn btn-outline-primary btn-sm">Pedir reserva</a>
            @endguest
        </header>

        <nav class="d-flex flex-wrap gap-2 mb-2" aria-label="Áreas">
            @foreach (\App\Support\LabAreas::options() as $key => $label)
                <a href="{{ route('labs.public', ['area' => $key]) }}" class="btn btn-sm {{ $area === $key ? 'btn-primary' : 'btn-outline-primary' }}" @if ($area === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($labs->isEmpty())
            <div class="alert alert-secondary">Nenhum laboratório desta área disponível no calendário.</div>
        @else
            <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Laboratórios">
                @foreach ($labs as $l)
                    <a href="{{ $url(['lab' => $l->id, 'semana' => $monday->toDateString()]) }}"
                        class="btn btn-sm {{ $l->id === $lab->id ? 'btn-dark' : 'btn-outline-secondary' }}" @if ($l->id === $lab->id) aria-current="page" @endif>{{ $l->code }}</a>
                @endforeach
            </nav>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <a class="btn btn-sm btn-outline-secondary" href="{{ $url(['lab' => $lab->id, 'semana' => $monday->copy()->subWeek()->toDateString()]) }}" aria-label="Semana anterior">‹</a>
                <strong class="small">{{ $lab->name !== $lab->code ? $lab->name . ' · ' : '' }}{{ $lab->code }} — semana de {{ $days->first()->format('d/m') }} a {{ $days->last()->format('d/m/Y') }}</strong>
                <a class="btn btn-sm btn-outline-secondary" href="{{ $url(['lab' => $lab->id, 'semana' => $monday->copy()->addWeek()->toDateString()]) }}" aria-label="Próxima semana">›</a>
                <a class="btn btn-sm btn-link" href="{{ $url(['lab' => $lab->id]) }}">Esta semana</a>
                <span class="ms-auto small text-muted">
                    {{ $lab->computers ? $lab->computers . ($lab->isComputerLab() ? ' computadores' : ' vagas') : '' }}
                </span>
            </div>

            <div class="d-flex flex-wrap gap-3 small mb-2" aria-hidden="true">
                <span><span class="lab-chip lab-grade">Aula</span> aula regular do semestre</span>
                <span><span class="lab-chip lab-manual">Reservado</span> evento, prova ou reserva avulsa</span>
                <span><span class="lab-chip lab-free">Livre</span> monitoria e estudo</span>
            </div>

            {{-- Celular: um dia por vez, em lista. --}}
            @php $todayIndex = $days->search(fn ($d) => $d->isToday()); $todayIndex = $todayIndex === false ? 0 : $todayIndex; @endphp
            <div class="d-md-none" data-day-view>
                <div class="btn-group w-100 mb-2" role="tablist" aria-label="Dia da semana">
                    @foreach ($days as $d => $date)
                        <button type="button" class="btn btn-sm {{ $d === $todayIndex ? 'btn-dark' : 'btn-outline-secondary' }}" role="tab"
                            aria-selected="{{ $d === $todayIndex ? 'true' : 'false' }}" data-day="{{ $d }}">{{ $dayShort[$date->dayOfWeekIso] }}<br><small>{{ $date->format('d/m') }}</small></button>
                    @endforeach
                </div>
                @foreach ($days as $d => $date)
                    <ul class="list-group mb-2" data-day-list="{{ $d }}" @if ($d !== $todayIndex) hidden @endif>
                        @foreach ($rows as $row)
                            @php $cell = $row['cells'][$d]; $r = $cell['reservation'] ?? null; @endphp
                            @continue($cell['kind'] === 'closed')
                            <li class="list-group-item d-flex align-items-center gap-2 py-2">
                                <span class="small text-muted text-nowrap" style="width: 6.5rem">{{ $row['shift'] }} {{ $row['letter'] }}<br>{{ $row['start'] }}–{{ $row['end'] }}</span>
                                @if ($cell['kind'] === 'grade')
                                    <span class="lab-chip lab-grade flex-grow-1"><strong>{{ $r->turma?->subject?->short_label ?? 'Aula' }}</strong> · {{ $r->turma?->class_code }}</span>
                                @elseif ($cell['kind'] === 'manual')
                                    <span class="lab-chip lab-manual flex-grow-1"><strong>{{ $r->tipo_solicitacao }}</strong> · {{ $r->titulo }}</span>
                                @else
                                    <span class="lab-chip lab-free flex-grow-1">Livre</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
            <script>
                document.querySelectorAll('[data-day-view] [data-day]').forEach(btn => btn.addEventListener('click', () => {
                    document.querySelectorAll('[data-day-view] [data-day]').forEach(b => { const on = b === btn; b.classList.toggle('btn-dark', on); b.classList.toggle('btn-outline-secondary', !on); b.setAttribute('aria-selected', on); });
                    document.querySelectorAll('[data-day-list]').forEach(l => { l.hidden = l.dataset.dayList !== btn.dataset.day; });
                }));
            </script>

            <div class="table-responsive d-none d-md-block">
                <table class="table table-bordered align-middle mb-0 lab-week">
                    <caption class="visually-hidden">Ocupação do laboratório {{ $lab->code }}</caption>
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
                                        @if ($cell['kind'] === 'grade')
                                            <div class="lab-chip lab-grade d-block" title="Aula · {{ $r->turma?->subject?->name }} ({{ $r->turma?->class_code }})">
                                                <strong>{{ $r->turma?->subject?->short_label ?? 'Aula' }}</strong>
                                                <small class="d-block text-truncate">{{ $r->turma?->class_code }}</small>
                                            </div>
                                        @elseif ($cell['kind'] === 'manual')
                                            <div class="lab-chip lab-manual d-block" title="{{ $r->tipo_solicitacao }} · {{ $r->titulo }}">
                                                <strong>{{ $r->tipo_solicitacao }}</strong>
                                                <small class="d-block text-truncate">{{ $r->titulo }}</small>
                                            </div>
                                        @elseif ($cell['kind'] === 'free')
                                            <span class="lab-chip lab-free d-block text-center">Livre</span>
                                        @else
                                            <span class="d-block text-center text-muted small">fechado</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-2 mb-0">Funcionamento: seg–sex 07:30–22:20; sábado 07:30–12:00.</p>
        @endif
    </div>

    <style>
        .lab-public .brand-mark { display: inline-grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: .6rem; background: #0c3d67; color: #e4b94f; font-weight: 800; font-size: 1.25rem; }
        .lab-week { table-layout: fixed; min-width: 720px; font-size: .8rem; background: #fff; }
        .lab-time { width: 8.5rem; white-space: nowrap; background: #fff; }
        .lab-shift th { background: #eef2f6 !important; font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #52606d; padding: .2rem .6rem; }
        .lab-today { background: #fff8e1 !important; }
        .lab-chip { display: inline-block; border-radius: .4rem; padding: .2rem .4rem; line-height: 1.2; font-size: .75rem; }
        .lab-grade { background: #dbe8f6; color: #0c3d67; border: 1px solid #b8cfe6; }
        .lab-manual { background: #fde2c4; color: #7a3d00; border: 1px solid #f5c48f; }
        .lab-free { background: #eaf6ef; color: #146c43; border: 1px solid #cbe9d6; }
    </style>
@endsection
