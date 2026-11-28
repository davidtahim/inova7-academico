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

                    <div class="alloc-head d-none d-xl-grid">
                        <span>Disciplina</span><span>Professor</span><span>Alunos · ED · H.A.</span><span>Dias</span><span>Horário</span><span>Sala</span><span></span>
                    </div>

                    @foreach ($offerings as $offering)
                        @php
                            $current = $inputs[$offering->id];
                            $mine = ($feedback['id'] ?? null) === $offering->id;
                            $value = fn ($key, $default) => $mine && ! ($feedback['saved'] ?? false) ? old($key, $default) : $default;
                            $profId = (int) $value('professor_id', $current['professor_id']);
                            $days = array_map('intval', (array) $value('weekdays', $current['weekdays']));
                            $chosenLetters = (array) $value('letters', $current['letters']);
                            $room = $value('room', $current['room']);
                            $link = $offering->matrixDiscipline;
                            $qualifiedIds = collect($qualified)->filter(fn ($ids) => in_array($offering->subject_id, $ids, true))->keys()->all();
                            $isDistance = strtoupper((string) $offering->modality) === 'DOL';
                        @endphp
                        <form method="POST" action="{{ route('allocation.update', $offering) }}" class="alloc-row {{ $mine ? (($feedback['saved'] ?? false) ? 'is-saved' : 'is-error') : '' }}"
                            id="oferta-{{ $offering->id }}" data-regular="{{ (float) ($link?->weekly_hours ?: 2) }}" data-ed-max="{{ $directedStudyMax }}">
                            @csrf
                            @method('PUT')
                            <div class="alloc-subject">
                                <strong>{{ $offering->subject->name }}</strong>
                                <small class="text-muted d-block">{{ $offering->subject->code }}</small>
                                @if ($link?->required_room_type === 'LABORATORIO_INFORMATICA')<span class="badge bg-warning-subtle text-warning-emphasis">Laboratório</span>@endif
                                @if ($link?->allows_shared_class)<span class="badge bg-success-subtle text-success-emphasis">Compartilhada</span>@endif
                                @if ($isDistance)<span class="badge bg-info-subtle text-info-emphasis">DOL / EaD</span>@endif
                            </div>
                            <div>
                                <label class="visually-hidden" for="p-{{ $offering->id }}">Professor</label>
                                <select id="p-{{ $offering->id }}" name="professor_id" class="form-select form-select-sm">
                                    <option value="">— A definir —</option>
                                    <optgroup label="Habilitados">
                                        @foreach ($professors->whereIn('id', $qualifiedIds) as $professor)
                                            <option value="{{ $professor->id }}" @selected($profId === $professor->id)>{{ $professor->name }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Outros professores">
                                        @foreach ($professors->whereNotIn('id', $qualifiedIds) as $professor)
                                            <option value="{{ $professor->id }}" @selected($profId === $professor->id)>{{ $professor->name }}</option>
                                        @endforeach
                                    </optgroup>
                                </select>
                            </div>
                            <div class="alloc-load">
                                <input type="number" min="0" max="500" name="student_count" class="form-control form-control-sm" data-students
                                    value="{{ $value('student_count', $offering->student_count) }}" placeholder="Alunos" aria-label="Quantidade de alunos">
                                <label class="form-check-label small text-nowrap" title="Estudo Dirigido">
                                    <input type="checkbox" class="form-check-input" name="directed_study" value="1" data-ed
                                        @checked((bool) $value('directed_study', $offering->directed_study))> ED
                                </label>
                                <input type="number" min="0" max="20" step="0.5" name="weekly_hours" class="form-control form-control-sm" data-hours
                                    value="{{ $value('weekly_hours', (float) $offering->weekly_hours) }}" aria-label="Hora-aula docente" title="Hora-aula docente">
                            </div>
                            <div class="alloc-toggles" role="group" aria-label="Dias">
                                @foreach ($weekdays as $day => $label)
                                    <input type="checkbox" class="btn-check" name="weekdays[]" value="{{ $day }}" id="d-{{ $offering->id }}-{{ $day }}" @checked(in_array($day, $days, true))>
                                    <label class="btn btn-outline-secondary btn-sm" for="d-{{ $offering->id }}-{{ $day }}">{{ $label }}</label>
                                @endforeach
                            </div>
                            <div class="alloc-toggles" role="group" aria-label="Blocos de horário">
                                @foreach ($letters as $letter => $slot)
                                    <input type="checkbox" class="btn-check" name="letters[]" value="{{ $letter }}" id="l-{{ $offering->id }}-{{ $letter }}" @checked(in_array($letter, $chosenLetters, true))>
                                    <label class="btn btn-outline-primary btn-sm" for="l-{{ $offering->id }}-{{ $letter }}" title="{{ $slot['start'] }}–{{ $slot['end'] }}">{{ $letter }}</label>
                                @endforeach
                            </div>
                            <div>
                                <label class="visually-hidden" for="r-{{ $offering->id }}">Sala</label>
                                <select id="r-{{ $offering->id }}" name="room" class="form-select form-select-sm">
                                    <option value="">— Sala —</option>
                                    @foreach ($rooms->sortBy(fn ($r) => [$link?->required_room_type && $r->type !== $link->required_room_type ? 1 : 0, $r->code]) as $roomOption)
                                        <option value="{{ $roomOption->code }}" @selected($room === $roomOption->code)>
                                            {{ $roomOption->code }}{{ $roomOption->type === 'LABORATORIO_INFORMATICA' ? ' (lab)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                            </div>

                            @if ($mine)
                                <div class="alloc-feedback">
                                    @if ($feedback['saved'] ?? false)
                                        <div class="text-success small fw-semibold">✓ Alocação salva.</div>
                                    @endif
                                    @foreach ($feedback['errors'] ?? [] as $error)
                                        <div class="text-danger small">✗ {{ $error }}</div>
                                    @endforeach
                                    @foreach ($feedback['warnings'] ?? [] as $warning)
                                        <div class="text-warning-emphasis small">⚠ {{ $warning }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </form>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="alert alert-secondary">Nenhuma turma para os filtros escolhidos.</div>
        @endforelse
    </div>

    <style>
        .alloc-head, .alloc-row {
            display: grid;
            grid-template-columns: minmax(180px, 1.5fr) minmax(170px, 1.2fr) 190px 250px 160px 120px 70px;
            gap: .5rem;
            align-items: center;
        }
        .alloc-head { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; padding: 0 .5rem .25rem; }
        .alloc-row { padding: .6rem .5rem; border-top: 1px solid #edf0f3; scroll-margin-top: 90px; }
        .alloc-row.is-saved { background: #f0fbf4; }
        .alloc-row.is-error { background: #fff5f5; }
        .alloc-subject strong { font-size: .85rem; }
        .alloc-load { display: flex; gap: .35rem; align-items: center; }
        .alloc-load input[type=number] { width: 64px; }
        .alloc-toggles { display: flex; flex-wrap: wrap; gap: 2px; }
        .alloc-toggles .btn { padding: .15rem .4rem; font-size: .75rem; }
        .alloc-feedback { grid-column: 1 / -1; }
        @media (max-width: 1199.98px) {
            .alloc-row { grid-template-columns: 1fr 1fr; }
            .alloc-subject, .alloc-feedback { grid-column: 1 / -1; }
        }
        @media (max-width: 575.98px) { .alloc-row { grid-template-columns: 1fr; } }
    </style>

    <script>
        // Regra da planilha: até N alunos a turma vira Estudo Dirigido (hora-aula 1; 0 sem alunos).
        document.querySelectorAll('.alloc-row').forEach(row => {
            const students = row.querySelector('[data-students]');
            const ed = row.querySelector('[data-ed]');
            const hours = row.querySelector('[data-hours]');
            students?.addEventListener('change', () => {
                if (students.value === '') return;
                const n = parseInt(students.value, 10);
                const directed = n <= parseInt(row.dataset.edMax, 10);
                ed.checked = directed;
                hours.value = directed ? (n === 0 ? 0 : 1) : row.dataset.regular;
            });
        });
    </script>
@endsection
