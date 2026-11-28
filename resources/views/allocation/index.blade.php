@extends('layouts.app')

@section('page-title', 'Montagem de horários')

@php
    $feedback = session('allocation');
    $copyReport = session('copy_report');
@endphp

@section('content')
    <div class="container-fluid py-4 px-0 allocation-page">
        <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-3">
            <div>
                <h2 class="h4 mb-1">Montagem de horários — {{ $term->code ?? 'sem semestre' }}</h2>
                <p class="text-muted mb-0">
                    Para cada disciplina da turma: professor, alunos, Estudo Dirigido, hora-aula, dias, blocos (AB/CD) e sala.
                    O sistema bloqueia choques de professor, sala e turma e respeita a disponibilidade docente.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if ($term?->isSchedulePublished())
                    <span class="badge bg-success">Publicada {{ $term->schedule_published_at->format('d/m/Y H:i') }}{{ $term->publisher ? ' por ' . $term->publisher->name : '' }}</span>
                    <form method="POST" action="{{ route('allocation.unpublish') }}" onsubmit="return confirm('Voltar a grade para rascunho? Alunos e professores deixam de vê-la.')">
                        @csrf<button class="btn btn-outline-warning btn-sm">Voltar para rascunho</button>
                    </form>
                @elseif ($term)
                    <span class="badge bg-warning text-dark">Rascunho — não visível para alunos e professores</span>
                    <form method="POST" action="{{ route('allocation.publish') }}"
                        onsubmit="return confirm('Publicar a grade {{ $term->code }}? Alunos e professores passam a ver os horários.{{ $conflicts->isNotEmpty() ? ' Ainda há ' . $conflicts->count() . ' choque(s).' : '' }}')">
                        @csrf<button class="btn btn-success btn-sm">Publicar grade</button>
                    </form>
                @endif
                <a href="{{ route('allocation.board') }}" class="btn btn-primary btn-sm">Grade global</a>
                <a href="{{ route('schedules.index', ['course' => $courseId]) }}" class="btn btn-outline-secondary btn-sm">Ver como o aluno vê</a>
            </div>
        </div>

        @if ($copyReport)
            <div class="alert alert-info">
                <strong>Grade {{ $copyReport['target'] }} montada a partir de {{ $copyReport['source'] }}:</strong>
                {{ $copyReport['copied'] }} com horário copiado,
                {{ $copyReport['professor_only'] }} só com professor (choque),
                {{ $copyReport['without_match'] }} sem equivalente,
                {{ $copyReport['skipped_distance'] }} EaD/DOL.
                @if ($copyReport['conflicts'] !== [])
                    <details class="mt-2"><summary>Ver choques ({{ count($copyReport['conflicts']) }})</summary>
                        <ul class="small mb-0 mt-1">@foreach ($copyReport['conflicts'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                    </details>
                @endif
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <form method="GET" class="card border-0 shadow-sm h-100">
                    <div class="card-body row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold mb-1" for="f-course">Curso</label>
                            <select id="f-course" name="course" class="form-select form-select-sm" onchange="this.form.submit()">
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" @selected($course->id === $courseId)>{{ $course->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1" for="f-period">Período</label>
                            <select id="f-period" name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                @foreach ($periods as $period)
                                    <option value="{{ $period }}" @selected((string) request('period') === (string) $period)>{{ $period }}º</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small fw-semibold mb-1" for="f-shift">Turno</label>
                            <select id="f-shift" name="shift" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                @foreach (['MANHÃ', 'TARDE', 'NOITE'] as $shift)
                                    <option value="{{ $shift }}" @selected(request('shift') === $shift)>{{ $shift }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        @if ($conflicts->isEmpty())
                            <div class="text-success fw-semibold">✓ Nenhum choque no semestre</div>
                        @else
                            <div class="text-danger fw-semibold">{{ $conflicts->count() }} choque(s) no semestre</div>
                            <details class="small"><summary>Detalhes</summary>
                                <ul class="mb-0 mt-1">@foreach ($conflicts->take(20) as $c)<li>{{ $c['message'] }} {{ $c['left'] }} × {{ $c['right'] }}</li>@endforeach</ul>
                            </details>
                        @endif
                        @if ($sourceTerms->isNotEmpty())
                            <form method="POST" action="{{ route('allocation.copy') }}" class="d-flex gap-2 mt-2"
                                onsubmit="return confirm('Preencher as disciplinas sem horário com a alocação do semestre escolhido? Disciplinas já montadas não são alteradas.')">
                                @csrf
                                <select name="source_term_id" class="form-select form-select-sm" aria-label="Semestre de origem">
                                    @foreach ($sourceTerms as $source)
                                        <option value="{{ $source->id }}">Copiar de {{ $source->code }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-primary text-nowrap" type="submit">Montar</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @forelse ($classes as $classCode => $offerings)
            @php
                $first = $offerings->first();
                $shift = $first->shift;
                $letters = \App\Services\TimeBlocks::forShift($shift);
                $entries = \App\Http\Controllers\ScheduleController::entries($offerings);
                $scheduled = $offerings->filter(fn ($o) => $o->slots->isNotEmpty())->count();
            @endphp
            <section class="card border-0 shadow-sm mb-4" id="turma-{{ $classCode }}">
                <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div>
                        <strong>Turma {{ $classCode }}</strong>
                        <span class="text-muted">· {{ $first->period }}º período · {{ $shift }}</span>
                    </div>
                    <span class="badge {{ $scheduled === $offerings->count() ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ $scheduled }} de {{ $offerings->count() }} com horário
                    </span>
                </div>
                <div class="card-body pt-0">
                    <details class="mb-3" @if ($scheduled > 0) open @endif>
                        <summary class="small text-muted mb-2">Grade semanal da turma</summary>
                        @include('partials.weekly-grid', ['compact' => true])
                    </details>

                    <ul class="list-unstyled mb-0 alloc-list">
                        @foreach ($offerings as $offering)
                            @php
                                $m = $allocModal[$offering->id];
                                $mine = ($feedback['id'] ?? null) === $offering->id;
                                $link = $offering->matrixDiscipline;
                                $practical = $m['practical'];
                                $isDistance = $m['distance'];
                                $expected = $m['expected'];
                                $placements = (array) $m['placements'];
                                $placed = array_sum(array_map('count', $placements));
                                $professor = $allocContext['professors']->firstWhere('id', $m['professor']) ?? $offering->assignments->first()?->professor;
                                $room = $m['room'];
                                $summary = collect($placements)->map(fn ($ls, $d) => $weekdays[$d] . ' ' . implode('', $ls))->implode(' · ');
                                $status = $isDistance ? ['EaD', 'info'] : ($expected > 0 && $placed === $expected ? ['Completa', 'success'] : ($placed === 0 ? ['Sem horário', 'secondary'] : ['Faltam ' . max(0, $expected - $placed) . ' aula(s)', 'warning']));
                                if (! $isDistance && $placed > $expected && $expected > 0) $status = ['Aulas a mais', 'danger'];
                            @endphp
                            <li>
                                <button type="button" class="alloc-item {{ $mine ? (($feedback['saved'] ?? false) ? 'is-saved' : 'is-error') : '' }}"
                                    id="oferta-{{ $offering->id }}" data-edit="{{ $offering->id }}" aria-haspopup="dialog">
                                    <span class="alloc-item-main">
                                        <span class="fw-semibold d-block text-truncate">{{ $offering->subject->name }}</span>
                                        <span class="small text-muted">
                                            {{ $offering->subject->code }}
                                            @if ($practical) · <span class="text-warning-emphasis">{{ $link?->required_room_type ? \Illuminate\Support\Str::lower(\App\Models\Room::TYPES[$link->required_room_type] ?? 'laboratório') : 'laboratório' }}</span>@endif
                                            @if ($offering->directed_study) · Estudo Dirigido @endif
                                        </span>
                                    </span>
                                    <span class="alloc-item-col">
                                        <span class="alloc-label">Professor</span>
                                        @if ($professor)
                                            {{ $professor->name }}
                                        @else
                                            <span class="text-warning-emphasis">A definir</span>
                                        @endif
                                    </span>
                                    <span class="alloc-item-col">
                                        <span class="alloc-label">Horário</span>
                                        {{ $summary ?: '—' }}
                                    </span>
                                    <span class="alloc-item-col alloc-item-room">
                                        <span class="alloc-label">Sala</span>
                                        {{ $room ?: '—' }}
                                    </span>
                                    <span class="alloc-item-status"><span class="badge text-bg-{{ $status[1] }} {{ $status[1] === 'warning' ? 'text-dark' : '' }}">{{ $status[0] }}</span></span>
                                    <span class="alloc-item-edit" aria-hidden="true">Editar ›</span>
                                </button>
                                @if ($mine && ($feedback['saved'] ?? false))
                                    <div class="small text-success px-3 pb-2">✓ Alocação salva.
                                        @foreach ($feedback['warnings'] ?? [] as $warning)<span class="text-warning-emphasis d-block">⚠ {{ $warning }}</span>@endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @empty
            <div class="alert alert-secondary">Nenhuma turma para os filtros escolhidos.</div>
        @endforelse
    </div>

    @include('partials.allocation-modal')

    <style>
        .alloc-list > li + li { border-top: 1px solid #edf0f3; }
        .alloc-item { display: grid; grid-template-columns: minmax(200px, 2fr) minmax(140px, 1.3fr) minmax(110px, 1fr) 90px 130px 60px; gap: .75rem; align-items: center;
            width: 100%; text-align: left; background: none; border: 0; padding: .7rem .75rem; border-radius: .5rem; color: inherit; scroll-margin-top: 90px; }
        .alloc-item:hover { background: #f4f8fc; }
        .alloc-item:focus-visible { outline: 3px solid #0c3d67; outline-offset: -2px; }
        .alloc-item.is-saved { background: #f0fbf4; }
        .alloc-item.is-error { background: #fff5f5; }
        .alloc-item-main { min-width: 0; }
        .alloc-item-col { font-size: .85rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
        .alloc-label { display: none; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; }
        .alloc-item-edit { color: #1264a3; font-size: .8rem; font-weight: 600; text-align: right; }
        @media (max-width: 991.98px) {
            .alloc-item { grid-template-columns: 1fr 1fr; }
            .alloc-item-main { grid-column: 1 / -1; }
            .alloc-label { display: block; }
            .alloc-item-edit { display: none; }
        }
    </style>
@endsection
