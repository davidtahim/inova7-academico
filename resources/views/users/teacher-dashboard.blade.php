@extends('layouts.app')

@section('page-title', 'Meu painel docente')

@php
    $initials = strtoupper(Str::of($user->name)->split('/\s+/')->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->implode(''));
    $editOpen = $professor && ($errors->professional->any() || request()->boolean('editar'));
    $formatHours = fn ($value) => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',');
@endphp

@section('content')
    <div class="container py-4 teacher-dashboard">

        {{-- Cabeçalho --}}
        <section class="card border-0 shadow-sm card-soft mb-4">
            <div class="card-body d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    @if ($user->photo_path)
                        <img src="{{ Storage::disk('public')->url($user->photo_path) }}" alt="" class="rounded-circle td-avatar">
                    @else
                        <span class="td-avatar d-inline-flex align-items-center justify-content-center rounded-circle fw-bold">{{ $initials }}</span>
                    @endif
                    <div>
                        <h2 class="h4 mb-1">{{ $professor->name ?? $user->name }}</h2>
                        <div class="text-muted small">{{ $user->email }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="badge bg-primary-subtle text-primary-emphasis">Semestre {{ $term->code ?? '—' }}</span>
                            @if ($professor)
                                <span class="status-badge {{ $professor->active ? 'ativa' : 'inativa' }}">{{ $professor->active ? 'Ativo' : 'Inativo' }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary btn-sm">Alterar senha</a>
                    @if ($professor)
                        <a href="{{ route('teacher.availability.pdf') }}" class="btn btn-outline-success btn-sm">Gerar CCG-FOR-02</a>
                    @endif
                </div>
            </div>
        </section>

        @if (! $professor)
            <div class="alert alert-warning">
                <strong>Seu usuário ainda não está vinculado a um cadastro docente.</strong>
                Procure a coordenação do curso para concluir o vínculo; depois disso, suas turmas e pendências aparecem aqui.
            </div>
        @else
            {{-- Checklist de prontidão para o semestre --}}
            <section class="mb-4" aria-labelledby="checklist-title">
                <h3 id="checklist-title" class="h6 text-uppercase text-secondary mb-3">Prepare-se para {{ $term->code ?? 'o semestre' }}</h3>
                <ol class="row g-3 list-unstyled mb-0">
                    @php
                        $steps = [
                            ['key' => 'professional', 'title' => 'Dados funcionais', 'href' => route('teacher.dashboard') . '?editar=1#dados-funcionais', 'cta' => 'Completar'],
                            ['key' => 'qualification', 'title' => 'Habilitação em disciplinas', 'href' => route('teacher.subjects'), 'cta' => 'Escolher disciplinas'],
                            ['key' => 'availability', 'title' => 'Disponibilidade de horários', 'href' => route('teacher.availability'), 'cta' => 'Preencher grade'],
                        ];
                    @endphp
                    @foreach ($steps as $i => $step)
                        @php
                            $done = $checklist[$step['key']]['done'] ?? false;
                        @endphp
                        <li class="col-sm-6 col-xl-3">
                            <a href="{{ $step['href'] }}" class="card h-100 border-0 shadow-sm text-decoration-none td-step {{ $done ? 'is-done' : 'is-pending' }}">
                                <div class="card-body d-flex gap-3">
                                    <span class="td-step-icon" aria-hidden="true">{{ $done ? '✓' : $i + 1 }}</span>
                                    <span>
                                        <span class="d-block fw-semibold text-body">{{ $step['title'] }}</span>
                                        <small class="d-block text-muted">{{ $checklist[$step['key']]['detail'] ?? '' }}</small>
                                        <small class="d-block mt-1 {{ $done ? 'text-success' : 'text-primary fw-semibold' }}">
                                            {{ $done ? 'Concluído · revisar' : $step['cta'] . ' →' }}
                                        </small>
                                    </span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                    <li class="col-sm-6 col-xl-3">
                        <a href="{{ route('teacher.availability.pdf') }}" class="card h-100 border-0 shadow-sm text-decoration-none td-step is-final">
                            <div class="card-body d-flex gap-3">
                                <span class="td-step-icon" aria-hidden="true">4</span>
                                <span>
                                    <span class="d-block fw-semibold text-body">Formulário CCG-FOR-02</span>
                                    <small class="d-block text-muted">PDF com sua disponibilidade</small>
                                    <small class="d-block mt-1 text-primary fw-semibold">Gerar PDF →</small>
                                </span>
                            </div>
                        </a>
                    </li>
                </ol>
            </section>

            {{-- Dados funcionais --}}
            <section class="card border-0 shadow-sm card-soft mb-4" id="dados-funcionais" aria-labelledby="dados-title">
                <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div>
                        <h3 id="dados-title" class="h5 mb-0">Dados funcionais</h3>
                        <small class="text-muted">Usados pela coordenação na alocação de turmas e no CCG-FOR-02.</small>
                    </div>
                </div>

                @if ($missingFields !== [] && ! $editOpen)
                    <div class="alert alert-warning mx-3 mb-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <span>Complete seu cadastro: falta {{ implode(', ', $missingFields) }}.</span>
                        <button type="button" class="btn btn-sm btn-warning" data-edit-toggle>Completar agora</button>
                    </div>
                @endif

                <div class="card-body" data-view-block @if ($editOpen) hidden @endif>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 td-stack-table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Matrícula</th>
                                    <th>Chapa</th>
                                    <th>Formação</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td data-label="Nome"><strong>{{ $professor->name }}</strong></td>
                                    <td data-label="Matrícula">{!! filled($professor->registration) ? e($professor->registration) : '<span class="text-danger">Não informada</span>' !!}</td>
                                    <td data-label="Chapa">{!! filled($professor->chapa) ? e($professor->chapa) : '<span class="text-danger">Não informada</span>' !!}</td>
                                    <td data-label="Formação">
                                        @if (in_array($professor->qualification, $qualificationOptions, true))
                                            {{ $professor->qualification }}
                                        @else
                                            <span class="text-danger">Não informada</span>
                                        @endif
                                    </td>
                                    <td data-label="Status">
                                        <span class="status-badge {{ $professor->active ? 'ativa' : 'inativa' }}">{{ $professor->active ? 'Ativo' : 'Inativo' }}</span>
                                    </td>
                                    <td class="text-end" data-label="Ações">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-edit-toggle>Editar</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @if ($professor->lattes)
                        <div class="small mt-2"><span class="text-muted">Lattes:</span> <a href="{{ $professor->lattes }}" target="_blank" rel="noopener">{{ $professor->lattes }}</a></div>
                    @endif
                </div>

                <form method="POST" action="{{ route('profile.professional.update') }}" class="card-body border-top" data-edit-block @unless ($editOpen) hidden @endunless novalidate>
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="pf-name" class="form-label fw-semibold">Nome completo</label>
                            <input id="pf-name" name="name" type="text" class="form-control @error('name', 'professional') is-invalid @enderror"
                                value="{{ old('name', $professor->name) }}" required autocomplete="name">
                            @error('name', 'professional')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="pf-registration" class="form-label fw-semibold">Matrícula</label>
                            <input id="pf-registration" name="registration" type="text" class="form-control @error('registration', 'professional') is-invalid @enderror"
                                value="{{ old('registration', $professor->registration) }}" required placeholder="Ex.: UNI727720">
                            @error('registration', 'professional')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="pf-chapa" class="form-label fw-semibold">Chapa</label>
                            <input id="pf-chapa" name="chapa" type="text" class="form-control @error('chapa', 'professional') is-invalid @enderror"
                                value="{{ old('chapa', $professor->chapa) }}" required>
                            <div class="form-text">Número funcional no RH/TOTVS.</div>
                            @error('chapa', 'professional')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="pf-qualification" class="form-label fw-semibold">Formação (maior titulação)</label>
                            <select id="pf-qualification" name="qualification" class="form-select @error('qualification', 'professional') is-invalid @enderror" required>
                                <option value="" disabled @selected(! in_array(old('qualification', $professor->qualification), $qualificationOptions, true))>Selecione…</option>
                                @foreach ($qualificationOptions as $option)
                                    <option value="{{ $option }}" @selected(old('qualification', $professor->qualification) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('qualification', 'professional')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label for="pf-lattes" class="form-label fw-semibold">Currículo Lattes <span class="text-muted fw-normal">(opcional)</span></label>
                            <input id="pf-lattes" name="lattes" type="text" inputmode="url" data-strip-scheme class="form-control @error('lattes', 'professional') is-invalid @enderror"
                                value="{{ old('lattes', $professor->lattes) }}" placeholder="lattes.cnpq.br/1234567890123456">
                            @error('lattes', 'professional')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <span class="form-label fw-semibold d-block">Status</span>
                            <span class="status-badge {{ $professor->active ? 'ativa' : 'inativa' }}">{{ $professor->active ? 'Ativo' : 'Inativo' }}</span>
                            <div class="form-text">Definido pela coordenação.</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-light" data-edit-toggle>Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar dados</button>
                    </div>
                </form>
            </section>

            {{-- Turmas do semestre --}}
            <section class="card border-0 shadow-sm card-soft" aria-labelledby="turmas-title">
                <div class="card-header bg-white border-0">
                    <h3 id="turmas-title" class="h5 mb-0">Minhas turmas em {{ $term->code ?? '—' }}</h3>
                    <small class="text-muted">Alocações feitas pela coordenação. Troque o semestre no seletor do topo.</small>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3 mb-3">
                        @foreach ([
                            ['label' => 'Turmas', 'value' => $stats['classes']],
                            ['label' => 'Aulas por semana', 'value' => $formatHours($stats['weekly_hours'])],
                            ['label' => 'Alunos', 'value' => $stats['students'] ?: '—'],
                            ['label' => 'Confirmadas', 'value' => $stats['classes'] ? $stats['confirmed'] . ' de ' . $stats['classes'] : '—'],
                        ] as $kpi)
                            <div class="col-6 col-lg-3">
                                <div class="td-kpi">
                                    <div class="td-kpi-value">{{ $kpi['value'] }}</div>
                                    <div class="td-kpi-label">{{ $kpi['label'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($assignments->isEmpty())
                        <div class="td-empty text-center py-4">
                            <div class="fs-2" aria-hidden="true">🗓️</div>
                            <p class="fw-semibold mb-1">Nenhuma turma alocada para {{ $term->code ?? 'este semestre' }} ainda.</p>
                            <p class="text-muted small mb-3">A coordenação usa sua habilitação e sua disponibilidade para sugerir turmas. Mantenha as duas atualizadas.</p>
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <a href="{{ route('teacher.subjects') }}" class="btn btn-sm btn-outline-primary">Minhas disciplinas</a>
                                <a href="{{ route('teacher.availability') }}" class="btn btn-sm btn-outline-primary">Minha disponibilidade</a>
                            </div>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 td-stack-table">
                                <thead>
                                    <tr>
                                        <th>Disciplina</th>
                                        <th>Turma</th>
                                        <th>Curso · período</th>
                                        <th>Turno</th>
                                        <th>Horários</th>
                                        <th class="text-end">Aulas/sem</th>
                                        <th class="text-end">Alunos</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($assignments as $assignment)
                                        @php
                                            $offering = $assignment->offering;
                                            $status = $statusLabels[$assignment->status] ?? ['label' => $assignment->status, 'class' => 'bg-light text-dark'];
                                            $slots = $offering->slots->sortBy(fn ($slot) => [$slot->weekday, $slot->starts_at]);
                                        @endphp
                                        <tr>
                                            <td data-label="Disciplina">
                                                <strong>{{ $offering->subject->name ?? '—' }}</strong>
                                                <br><small class="text-muted">{{ $offering->subject->code ?? '' }}</small>
                                                @if (! in_array((int) $offering->subject_id, $qualifiedIds, true))
                                                    <br><span class="badge bg-warning-subtle text-warning-emphasis" title="Esta disciplina não está na sua habilitação">Fora da habilitação</span>
                                                @endif
                                            </td>
                                            <td data-label="Turma"><code>{{ $offering->class_code }}</code></td>
                                            <td data-label="Curso · período">{{ $offering->course->name ?? '—' }}<br><small class="text-muted">{{ $offering->period }}º período</small></td>
                                            <td data-label="Turno">{{ $offering->shift }}</td>
                                            <td data-label="Horários" class="small">
                                                @if (! $term?->isSchedulePublished())
                                                    <span class="text-muted">Em elaboração</span>
                                                @else
                                                @forelse ($slots as $slot)
                                                    <div>{{ $weekdays[$slot->weekday] ?? $slot->weekday }} {{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}@if ($slot->room) · {{ $slot->room }}@endif</div>
                                                @empty
                                                    <span class="text-muted">A definir</span>
                                                @endforelse
                                                @endif
                                            </td>
                                            <td data-label="Aulas/sem" class="text-end">{{ $formatHours($assignment->weekly_hours ?: $offering->weekly_hours) }}</td>
                                            <td data-label="Alunos" class="text-end">{{ $offering->student_count ?? '—' }}</td>
                                            <td data-label="Status"><span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </div>

    <style>
        .td-avatar { width: 64px; height: 64px; object-fit: cover; background: #f0b429; color: #0b2545; font-size: 1.3rem; }
        .td-step { transition: transform .15s ease, box-shadow .15s ease; }
        .td-step:hover, .td-step:focus-visible { transform: translateY(-2px); }
        .td-step-icon { flex: 0 0 2rem; height: 2rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .td-step.is-done .td-step-icon { background: #d1e7dd; color: #0f5132; }
        .td-step.is-pending { box-shadow: inset 3px 0 0 #f0b429, 0 .125rem .25rem rgba(0,0,0,.075) !important; }
        .td-step.is-pending .td-step-icon { background: #fff3cd; color: #664d03; }
        .td-step.is-final .td-step-icon { background: #e7f1ff; color: #084298; }
        .td-kpi { background: #f6f8fb; border-radius: .75rem; padding: .9rem 1rem; height: 100%; }
        .td-kpi-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
        .td-kpi-label { font-size: .8rem; color: #6c757d; text-transform: uppercase; letter-spacing: .03em; }
        .td-empty { background: #f6f8fb; border-radius: .75rem; }

        /* Em telas pequenas as tabelas viram cartões rotulados, sem rolagem lateral. */
        @media (max-width: 767.98px) {
            .td-stack-table thead { display: none; }
            .td-stack-table, .td-stack-table tbody, .td-stack-table tr, .td-stack-table td { display: block; width: 100%; }
            .td-stack-table tr { border-bottom: 1px solid #e9ecef; padding: .5rem 0; }
            .td-stack-table td { border: 0; padding: .25rem 0; text-align: left !important; }
            .td-stack-table td::before { content: attr(data-label); display: block; font-size: .7rem; text-transform: uppercase; color: #6c757d; letter-spacing: .03em; }
        }
    </style>

    <script>
        (function () {
            const view = document.querySelector('[data-view-block]');
            const form = document.querySelector('[data-edit-block]');
            if (!view || !form) return;
            document.querySelectorAll('[data-edit-toggle]').forEach(button => button.addEventListener('click', () => {
                const editing = form.hidden;
                form.hidden = !editing;
                view.hidden = editing;
                // .d-flex usa display:flex !important, por isso a classe d-none em vez do atributo hidden.
                document.querySelectorAll('#dados-funcionais .alert-warning').forEach(alert => alert.classList.toggle('d-none', editing));
                if (editing) form.querySelector('input, select')?.focus();
            }));
        })();
    </script>
@endsection
