@extends('layouts.app')

@section('title', 'Diagnóstico da grade • Inova7 Acadêmico')
@section('page-title', 'Diagnóstico da grade')

@section('content')
    @php
        $levels = [
            'blocker' => ['Impede a grade de fechar', 'danger', 'Bloqueios'],
            'risk' => ['Risco', 'warning', 'Riscos'],
            'info' => ['Dado incompleto', 'secondary', 'Avisos'],
        ];
    @endphp
    <div class="container-fluid py-3 px-0 diagnosis-page">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
            <div>
                <h2 class="h4 mb-1">Por que a grade não fecha? — {{ $term->code }}</h2>
                <p class="text-muted small mb-0">
                    Analisa habilitações, professores confirmados, disponibilidade, salas e carga de cada turma
                    antes do resolvedor, e aponta o que precisa mudar. Considera o semestre inteiro, todos os cursos e turnos.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('allocation.diagnosis') }}" class="btn btn-sm btn-outline-secondary">↻ Analisar de novo</a>
                <a href="{{ route('allocation.board') }}" class="btn btn-sm btn-primary">Voltar à grade global</a>
            </div>
        </div>

        {{-- Veredito: a primeira coisa que a coordenação precisa saber. --}}
        @if ($summary['blockers'] > 0)
            <div class="alert alert-danger d-flex gap-3 align-items-start" role="status">
                <strong class="fs-4 lh-1" aria-hidden="true">!</strong>
                <div>
                    <strong>A grade não pode fechar como está.</strong>
                    {{ $summary['blockers'] }} {{ $summary['blockers'] === 1 ? 'impedimento atinge' : 'impedimentos atingem' }}
                    {{ $summary['blocked_classes'] }} das {{ $summary['classes'] }} aulas semanais.
                    Nenhum tempo de resolvedor resolve isso: é preciso mudar o dado indicado em cada item.
                </div>
            </div>
        @else
            <div class="alert alert-success d-flex gap-3 align-items-start" role="status">
                <strong class="fs-4 lh-1" aria-hidden="true">✓</strong>
                <div>
                    <strong>Nenhum impedimento estrutural encontrado.</strong>
                    Os dados permitem fechar a grade. Se o resolvedor ainda terminar com restrições rígidas,
                    rode por mais tempo ou sem preservar as aulas já posicionadas.
                </div>
            </div>
        @endif

        <div class="row g-2 mb-3">
            @foreach ([
                ['Bloqueios', $summary['blockers'], 'danger', 'impedem fechar'],
                ['Riscos', $summary['risks'], 'warning', 'apertado ou acima do limite'],
                ['Avisos', $summary['infos'], 'secondary', 'dados incompletos'],
                ['Aulas fora da grade', $summary['missing'], $summary['missing'] ? 'danger' : 'success', 'na grade atual, de ' . $summary['classes']],
            ] as [$label, $value, $tone, $note])
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body py-2">
                            <div class="small text-muted text-uppercase">{{ $label }}</div>
                            <div class="fs-3 fw-bold text-{{ $tone === 'secondary' ? 'body' : $tone }}">{{ $value }}</div>
                            <div class="small text-muted">{{ $note }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Mapa de disputa por horário (RF02): onde faltam professores livres. --}}
        @if ($contention)
            <section class="card border-0 shadow-sm mb-3" aria-labelledby="contention-title">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                        <h3 class="h6 mb-0" id="contention-title">Mapa de disputa por horário</h3>
                        <span class="small text-muted">professores livres em cada horário × turmas do turno. Passe o mouse para ver quem está livre.</span>
                    </div>
                    <div class="d-flex flex-wrap gap-3 small mb-2" aria-hidden="true">
                        <span><span class="ct-swatch ct-none"></span> ninguém livre</span>
                        <span><span class="ct-swatch ct-short"></span> menos professores que turmas</span>
                        <span><span class="ct-swatch ct-tight"></span> apertado</span>
                        <span><span class="ct-swatch ct-ok"></span> folga</span>
                    </div>
                    <div class="row g-3">
                        @foreach ($contention as $shift => $map)
                            <div class="col-lg-6 col-xxl-4">
                                <div class="small fw-semibold mb-1">
                                    {{ ucfirst($map['label']) }} · {{ $map['turmas'] }} {{ $map['turmas'] === 1 ? 'turma' : 'turmas' }} · {{ $map['professors'] }} professores
                                </div>
                                <div class="table-responsive">
                                <table class="table table-sm table-bordered text-center mb-1 ct-table">
                                    <caption class="visually-hidden">Disputa na {{ $map['label'] }}</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col"></th>
                                            @foreach (\App\Services\ScheduleDiagnostics::DAYS as $day)
                                                <th scope="col">{{ $day }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($map['letters'] as $letter => $start)
                                            <tr>
                                                <th scope="row" class="text-nowrap">{{ $letter }} <small class="text-muted fw-normal">{{ $start }}</small></th>
                                                @foreach (array_keys(\App\Services\ScheduleDiagnostics::DAYS) as $d)
                                                    @php($c = $map['cells'][$d . '|' . $start])
                                                    <td class="ct-{{ $c['level'] }}"
                                                        title="{{ $c['free'] }} com grade livre{{ $c['preferred'] ? ' (' . $c['preferred'] . ' preferem)' : '' }}{{ $map['no_grid'] ? ' + ' . $map['no_grid'] . ' sem grade' : '' }}{{ $c['names'] ? ': ' . implode(', ', $c['names']) : '' }}">
                                                        {{ $c['supply'] }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                </div>
                                @if ($map['no_grid'])
                                    <div class="small text-muted">Inclui {{ $map['no_grid'] }} {{ $map['no_grid'] === 1 ? 'professor sem grade, contado' : 'professores sem grade, contados' }} como livre{{ $map['no_grid'] === 1 ? '' : 's' }} em todos os horários.</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($issues === [])
            <p class="text-muted">Nada a apontar para este semestre.</p>
        @else
            <div class="d-flex flex-wrap gap-2 mb-2" role="group" aria-label="Filtrar por gravidade">
                <button type="button" class="btn btn-sm btn-outline-dark active" data-filter="all" aria-pressed="true">Todos ({{ count($issues) }})</button>
                @foreach ($levels as $key => [$long, $tone, $plural])
                    @php($n = collect($issues)->where('level', $key)->count())
                    @if ($n)
                        <button type="button" class="btn btn-sm btn-outline-{{ $tone }}" data-filter="{{ $key }}" aria-pressed="false">{{ $plural }} ({{ $n }})</button>
                    @endif
                @endforeach
            </div>

            <ol class="list-unstyled d-grid gap-2 mb-0" id="d-issues">
                @foreach ($issues as $issue)
                    @php([$long, $tone] = $levels[$issue['level']])
                    <li class="card border-0 shadow-sm diag-issue diag-{{ $issue['level'] }}" data-level="{{ $issue['level'] }}">
                        <div class="card-body py-3">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                <span class="badge bg-{{ $tone }} {{ $tone === 'warning' ? 'text-dark' : '' }}">{{ $long }}</span>
                                <h3 class="h6 mb-0">{{ $issue['title'] }}</h3>
                                @if ($issue['classes'] && $issue['offerings'])
                                    <span class="small text-muted ms-auto">{{ $issue['classes'] }} {{ $issue['classes'] === 1 ? 'aula/semana' : 'aulas/semana' }}</span>
                                @endif
                            </div>
                            <p class="mb-2 small">{{ $issue['detail'] }}</p>

                            @if ($issue['offerings'])
                                <details class="small mb-2">
                                    <summary class="text-muted">Ver {{ count($issue['offerings']) === 1 ? 'a disciplina envolvida' : 'as ' . count($issue['offerings']) . ' disciplinas/turmas envolvidas' }}</summary>
                                    <ul class="mt-1 mb-0">
                                        @foreach ($issue['offerings'] as $o)
                                            <li>
                                                {{ $o['label'] }} —
                                                <span class="{{ $o['placed'] < $o['expected'] ? 'text-danger' : 'text-success' }}">
                                                    {{ $o['placed'] }}/{{ $o['expected'] }} aulas na grade
                                                </span>
                                                ·
                                                @if ($o['professor'])
                                                    <span class="text-muted">{{ $o['professor'] }}</span>
                                                @else
                                                    <span class="text-danger">sem professor</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif

                            @if ($issue['actions'])
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="small fw-semibold text-muted align-self-center">O que fazer:</span>
                                    @foreach ($issue['actions'] as $action)
                                        <a href="{{ $action['url'] }}" class="btn btn-sm btn-outline-primary">{{ $action['label'] }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <style>
        .diag-issue { border-left: 4px solid transparent !important; }
        .diag-blocker { border-left-color: var(--bs-danger) !important; }
        .diag-risk { border-left-color: var(--bs-warning) !important; }
        .diag-info { border-left-color: var(--bs-secondary) !important; }
        .ct-table { font-size: .8rem; }
        .ct-table td { font-weight: 600; font-variant-numeric: tabular-nums; padding: .25rem; }
        .ct-table th { padding: .25rem .35rem; }
        .ct-none { background: #f8d7da !important; color: #842029; }
        .ct-short { background: #fde2c4 !important; color: #7a3d00; }
        .ct-tight { background: #fff3cd !important; color: #664d03; }
        .ct-ok { background: #d1e7dd !important; color: #0f5132; }
        .ct-swatch { display: inline-block; width: .9rem; height: .9rem; border-radius: 3px; vertical-align: -2px; border: 1px solid rgba(0,0,0,.08); }
    </style>
    <script>
        // Filtro por gravidade (sem recarregar a análise).
        document.querySelectorAll('[data-filter]').forEach(btn => btn.addEventListener('click', () => {
            const level = btn.dataset.filter;
            document.querySelectorAll('[data-filter]').forEach(b => {
                const on = b === btn;
                b.setAttribute('aria-pressed', on);
                b.classList.toggle('active', on);
            });
            document.querySelectorAll('.diag-issue').forEach(li => { li.hidden = level !== 'all' && li.dataset.level !== level; });
        }));
    </script>
@endsection
