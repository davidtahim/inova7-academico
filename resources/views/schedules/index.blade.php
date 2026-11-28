@extends('layouts.app')

@section('page-title', 'Horários das turmas')

@section('content')
    <div class="container-fluid py-4 px-0">
        @if ($isDraft)
            <div class="alert alert-warning">
                <strong>Rascunho:</strong> esta grade ainda não foi publicada. Só a coordenação a vê;
                alunos e professores veem "ainda não publicados".
            </div>
        @endif
        <div class="d-flex flex-wrap gap-3 align-items-end justify-content-between mb-3">
            <div>
                <h2 class="h4 mb-1">Horários das turmas — {{ $term->code ?? 'sem semestre' }}</h2>
                <p class="text-muted mb-0">Escolha o curso e a turma para ver a grade semanal com disciplina, professor e sala.</p>
            </div>
        </div>

        @if ($myOfferings->isNotEmpty())
            <section class="card border-0 shadow-sm mb-4 border-start border-4 border-primary" aria-labelledby="meu-horario">
                <div class="card-header bg-white border-0">
                    <h3 id="meu-horario" class="h5 mb-0">Meu horário — {{ $term->code }}</h3>
                    <small class="text-muted">Turmas em que você foi enturmado ({{ $myOfferings->count() }} disciplinas).</small>
                </div>
                <div class="card-body pt-0">
                    @foreach ($myGrids as $gridShift => $grid)
                        @if ($myGrids->count() > 1)<div class="fw-semibold small text-uppercase text-muted mt-2 mb-1">{{ $gridShift }}</div>@endif
                        @include('partials.weekly-grid', ['letters' => $grid['letters'], 'entries' => $grid['entries']])
                    @endforeach
                    <ul class="small mt-3 mb-0">
                        @foreach ($myOfferings as $mine)
                            <li>
                                <strong>{{ $mine->subject->name }}</strong> · turma <code>{{ $mine->class_code }}</code>
                                · {{ $mine->assignments->first()?->professor?->name ?? 'professor a definir' }}
                                @if ($mine->slots->isEmpty()) · <span class="text-muted">horário a definir</span>@endif
                                @if ($mine->directed_study) <span class="badge bg-info-subtle text-info-emphasis">Estudo dirigido</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
            <h3 class="h6 text-uppercase text-muted">Horários por turma</h3>
        @endif

        <form method="GET" class="card border-0 shadow-sm mb-3">
            <div class="card-body row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="sc-course" class="form-label small fw-semibold mb-1">Curso</label>
                    <select id="sc-course" name="course" class="form-select" onchange="this.form.turma.value=''; this.form.submit()">
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected($course->id === $courseId)>{{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="sc-turma" class="form-label small fw-semibold mb-1">Turma</label>
                    <select id="sc-turma" name="turma" class="form-select" onchange="this.form.submit()">
                        @foreach ($classOptions as $option)
                            <option value="{{ $option['code'] }}" @selected($option['code'] === $classCode)>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit">Ver horário</button>
                </div>
            </div>
        </form>

        @php $feedback = session('allocation'); @endphp
        @if ($feedback['saved'] ?? false)
            <div class="alert alert-success">✓ Alocação salva.
                @foreach ($feedback['warnings'] ?? [] as $warning)<span class="d-block text-warning-emphasis">⚠ {{ $warning }}</span>@endforeach
            </div>
        @endif

        @if ($offerings->isEmpty())
            <div class="alert alert-secondary">Nenhuma turma ofertada para este curso no semestre selecionado.</div>
        @else
            <section class="card border-0 shadow-sm mb-3" aria-label="Grade semanal da turma {{ $classCode }}">
                <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <strong>Turma {{ $classCode }} · {{ $shift }}</strong>
                    <small class="text-muted">
                        @if ($editable)Clique numa aula ou disciplina para editar · @endif
                        Aulas de 50 minutos
                    </small>
                </div>
                <div class="card-body pt-0">
                    @include('partials.weekly-grid', ['editable' => $editable])
                </div>
            </section>

            <section class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0"><strong>Disciplinas da turma</strong></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Disciplina</th><th>Professor</th><th>Dias e horários</th><th>Sala</th>@if ($editable)<th class="text-end"><span class="visually-hidden">Ações</span></th>@endif</tr></thead>
                        <tbody>
                            @foreach ($offerings as $offering)
                                @php
                                    $slots = $offering->slots->sortBy(fn ($s) => [$s->weekday, $s->starts_at]);
                                @endphp
                                @php $feedback = session('allocation'); $mine = ($feedback['id'] ?? null) === $offering->id; @endphp
                                <tr id="oferta-{{ $offering->id }}" class="{{ $mine ? (($feedback['saved'] ?? false) ? 'table-success' : 'table-danger') : '' }}">
                                    <td><strong>{{ $offering->subject->name }}</strong><br><small class="text-muted">{{ $offering->subject->code }}</small></td>
                                    <td>{{ $offering->assignments->first()?->professor?->name ?? 'A definir' }}</td>
                                    <td class="small">
                                        @forelse ($slots as $slot)
                                            <div>{{ $weekdays[$slot->weekday] ?? $slot->weekday }} {{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}</div>
                                        @empty
                                            <span class="text-muted">{{ strtoupper($offering->modality) === 'DOL' ? 'A distância (DOL)' : 'A definir' }}</span>
                                        @endforelse
                                    </td>
                                    <td>{{ $slots->pluck('room')->filter()->unique()->implode(', ') ?: '—' }}</td>
                                    @if ($editable)
                                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-edit="{{ $offering->id }}" aria-haspopup="dialog">Editar<span class="visually-hidden"> {{ $offering->subject->name }}</span></button></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>

    @if ($editable)
        @include('partials.allocation-modal', ['allocModal' => $allocModal, 'allocContext' => $allocContext])
    @endif
@endsection
