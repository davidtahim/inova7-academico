@extends('layouts.app')

@section('page-title', 'Enturmação')

@php
    $report = session('plan_report');
    $pendingTotal = $demand->sum(fn ($row) => $row['pending']->count());
    $uncovered = $demand->filter(fn ($row) => $row['pending']->isNotEmpty() && ! $row['offering']);
    $statusLabels = \App\Models\StudentSubjectStatus::LABELS;
@endphp

@section('content')
    <div class="container-fluid py-4 px-0">
        <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-3">
            <div>
                <h2 class="h4 mb-1">Enturmação — {{ $matrix?->name ?? 'sem alunos importados' }} · {{ $term->code ?? '—' }}</h2>
                <p class="text-muted mb-0">
                    Cada disciplina pendente no histórico do aluno vai para a turma ofertada no semestre.
                    Turmas com até {{ \App\Services\ScheduleAllocator::DIRECTED_STUDY_MAX_STUDENTS }} alunos viram Estudo Dirigido.
                </p>
            </div>
            @if ($matrices->count() > 1)
                <form method="GET">
                    <select name="matrix" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Matriz">
                        @foreach ($matrices as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $matrix?->id)>{{ $option->course->name }} — {{ $option->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        {{-- Importação dos históricos em PDF (sem CSV). Aberta quando ainda não há alunos. --}}
        <details class="card border-0 shadow-sm mb-3" id="import-histories" @if (! $matrix || $errors->has('historicos') || $errors->has('historicos.*')) open @endif>
            <summary class="card-header bg-white fw-semibold py-2">Importar históricos escolares (PDF)</summary>
            <div class="card-body">
                <form method="POST" action="{{ route('enrollment.import-histories') }}" enctype="multipart/form-data" class="row g-3 align-items-end" data-import-form>
                    @csrf
                    <div class="col-md-4">
                        <label for="import-matrix" class="form-label small fw-semibold">Matriz curricular dos alunos</label>
                        <select id="import-matrix" name="matrix" class="form-select" required>
                            @foreach ($importMatrices as $option)
                                <option value="{{ $option->id }}" @selected((int) old('matrix', $defaultImportMatrix) === $option->id)>{{ $option->course?->name }} — {{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="import-files" class="form-label small fw-semibold">Históricos (um ou vários PDFs)</label>
                        <input id="import-files" type="file" name="historicos[]" accept="application/pdf,.pdf" multiple required
                            class="form-control @if ($errors->has('historicos') || $errors->has('historicos.*')) is-invalid @endif" aria-describedby="import-help">
                        @foreach (['historicos', 'historicos.*'] as $field)
                            @error($field)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @endforeach
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary" data-import-submit>Importar históricos</button>
                    </div>
                    <p id="import-help" class="form-text small mb-0 col-12">
                        Lê matrícula, nome, turno e a situação de cada disciplina (cursada, dispensada, cursando, reprovada, falta cursar).
                        CPF, RG e data de nascimento são ignorados e os PDFs não ficam guardados no sistema.
                        Reimportar o mesmo aluno atualiza o histórico dele.
                    </p>
                </form>
                @if (session('import_failed'))
                    <div class="alert alert-warning small mt-3 mb-0">
                        Não foi possível ler: {{ implode(', ', session('import_failed')) }}. Confira se são PDFs do histórico com texto (não digitalizados).
                    </div>
                @endif
            </div>
        </details>
        <script>
            document.querySelector('[data-import-form]')?.addEventListener('submit', e => {
                const btn = e.target.querySelector('[data-import-submit]');
                btn.disabled = true; btn.textContent = 'Lendo históricos…';
            });
        </script>

        @if (! $matrix)
            <div class="alert alert-secondary">
                Nenhum aluno com matriz definida ainda. Importe os históricos acima para gerar a enturmação.
            </div>
        @else
            @if ($report)
                <div class="alert alert-info">
                    <strong>Enturmação gerada:</strong> {{ $report['enrolled'] }} matrículas em turmas para {{ $report['students'] }} alunos
                    ({{ $report['offerings_updated'] }} turmas com quantidade de alunos atualizada).
                    @if ($report['without_offering'] !== [])
                        <div class="small mt-1">Sem turma no semestre: @foreach ($report['without_offering'] as $name => $count){{ $name }} ({{ $count }})@if (! $loop->last), @endif @endforeach.</div>
                    @endif
                </div>
            @endif

            <div class="row g-3 mb-3">
                @foreach ([
                    ['Alunos', $students->count()],
                    ['Pendências (aluno × disciplina)', $pendingTotal],
                    ['Disciplinas com demanda', $demand->filter(fn ($r) => $r['pending']->isNotEmpty())->count()],
                    ['Sem turma em ' . ($term->code ?? ''), $uncovered->count() . ' (' . $uncovered->sum(fn ($r) => $r['pending']->count()) . ' alunos)'],
                    ['Alunos com choque de horário', $clashes->filter()->count()],
                ] as [$label, $value])
                    <div class="col-6 col-xl">
                        <div class="card border-0 shadow-sm h-100"><div class="card-body">
                            <div class="fs-4 fw-bold">{{ $value }}</div>
                            <div class="small text-muted text-uppercase">{{ $label }}</div>
                        </div></div>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('enrollment.plan') }}" class="card border-0 shadow-sm mb-3">
                @csrf
                <input type="hidden" name="matrix" value="{{ $matrix->id }}">
                <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                    <label class="form-check mb-0">
                        <input type="checkbox" class="form-check-input" name="include_in_progress" value="1">
                        <span class="form-check-label">Incluir disciplinas "matriculadas" sem resultado no histórico</span>
                    </label>
                    <button type="submit" class="btn btn-primary">Gerar enturmação em {{ $term->code }}</button>
                </div>
            </form>

            <section class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0"><strong>Demanda por disciplina</strong></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Per.</th><th>Disciplina</th><th class="text-end">Pendentes</th><th class="text-end">Matriculados s/ resultado</th>
                                <th>Turma em {{ $term->code }}</th><th class="text-end">Enturmados</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($demand as $row)
                                @php
                                    $offering = $row['offering'];
                                    $enrolled = $offering ? $offering->enrollments()->count() : 0;
                                @endphp
                                <tr class="{{ $row['pending']->isNotEmpty() && ! $offering ? 'table-warning' : '' }}">
                                    <td>{{ $row['period'] }}º</td>
                                    <td>
                                        <strong>{{ $row['subject']->name }}</strong><br><small class="text-muted">{{ $row['subject']->code }}</small>
                                    </td>
                                    <td class="text-end">
                                        @if ($row['pending']->isNotEmpty())
                                            <details><summary class="fw-semibold">{{ $row['pending']->count() }}</summary>
                                                <small class="d-block text-start">{{ $row['pending']->pluck('name')->implode(', ') }}</small>
                                            </details>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $row['in_progress']->count() ?: '—' }}</td>
                                    <td>
                                        @if ($offering)
                                            <code>{{ $offering->class_code }}</code> · {{ $offering->shift }}
                                            @if ($offering->directed_study)<span class="badge bg-info-subtle text-info-emphasis">ED</span>@endif
                                        @elseif ($row['pending']->isNotEmpty())
                                            <span class="text-danger fw-semibold">Sem turma</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $offering ? $enrolled : '—' }}</td>
                                    <td class="text-end">
                                        @if (! $offering && $row['pending']->isNotEmpty())
                                            <form method="POST" action="{{ route('enrollment.open-class') }}" class="d-flex gap-1 justify-content-end">
                                                @csrf
                                                <input type="hidden" name="matrix" value="{{ $matrix->id }}">
                                                <input type="hidden" name="subject_id" value="{{ $row['subject']->id }}">
                                                <select name="shift" class="form-select form-select-sm w-auto" aria-label="Turno da nova turma">
                                                    <option value="NOITE">Noite</option><option value="MANHÃ">Manhã</option>
                                                </select>
                                                <button class="btn btn-sm btn-outline-primary text-nowrap">Abrir turma ED</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0"><strong>Alunos</strong> <small class="text-muted">— situação do histórico e turmas em {{ $term->code }}</small></div>
                <div class="list-group list-group-flush">
                    @foreach ($students as $student)
                        @php
                            $pending = $student->subjectStatuses->whereIn('status', \App\Services\EnrollmentPlanner::PENDING);
                            $inProgress = $student->subjectStatuses->where('status', 'matriculada');
                            $termEnrollments = $student->enrollments->filter(fn ($e) => $e->offering && (int) $e->offering->academic_term_id === (int) $term->id);
                        @endphp
                        <details class="list-group-item">
                            <summary class="d-flex flex-wrap gap-2 justify-content-between">
                                <span><strong>{{ $student->name }}</strong> <small class="text-muted">{{ $student->registration_number }} · {{ $student->shift ?? '—' }}</small></span>
                                <span class="small">
                                    <span class="badge bg-warning-subtle text-warning-emphasis">{{ $pending->count() }} pendentes</span>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $inProgress->count() }} s/ resultado</span>
                                    <span class="badge bg-success-subtle text-success-emphasis">{{ $termEnrollments->count() }} turmas</span>
                                    @if (! empty($clashes[$student->id]))
                                        <span class="badge bg-danger">{{ count($clashes[$student->id]) }} choque(s) de horário</span>
                                    @endif
                                </span>
                            </summary>
                            <div class="row g-3 mt-1 small">
                                <div class="col-md-6">
                                    <div class="fw-semibold mb-1">Pendências</div>
                                    <ul class="mb-0">
                                        @forelse ($pending->sortBy(fn ($s) => [$s->matrix_period, $s->subject->name]) as $status)
                                            <li>{{ $status->subject->name }} <span class="text-muted">({{ $statusLabels[$status->status] }})</span></li>
                                        @empty
                                            <li class="text-muted">Nenhuma</li>
                                        @endforelse
                                        @foreach ($inProgress as $status)
                                            <li>{{ $status->subject->name }} <span class="text-muted">(matriculada em {{ $status->term_code }}, sem resultado)</span></li>
                                        @endforeach
                                    </ul>
                                </div>
                                @if (! empty($clashes[$student->id]))
                                    <div class="col-12">
                                        <div class="alert alert-danger py-2 mb-0">
                                            <strong>Choques na grade do aluno:</strong>
                                            <ul class="mb-0">@foreach ($clashes[$student->id] as $clash)<li>{{ $clash }}</li>@endforeach</ul>
                                        </div>
                                    </div>
                                @endif
                                <div class="col-md-6">
                                    <div class="fw-semibold mb-1">Turmas em {{ $term->code }}</div>
                                    <ul class="mb-0">
                                        @forelse ($termEnrollments as $enrollment)
                                            <li><code>{{ $enrollment->offering->class_code }}</code> {{ $enrollment->offering->subject->name }}</li>
                                        @empty
                                            <li class="text-muted">Ainda não enturmado</li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
