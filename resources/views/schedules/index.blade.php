@extends('layouts.app')

@section('page-title', 'Horários das turmas')

@section('content')
    <div class="container-fluid py-4 px-0">
        <div class="d-flex flex-wrap gap-3 align-items-end justify-content-between mb-3">
            <div>
                <h2 class="h4 mb-1">Horários das turmas — {{ $term->code ?? 'sem semestre' }}</h2>
                <p class="text-muted mb-0">Escolha o curso e a turma para ver a grade semanal com disciplina, professor e sala.</p>
            </div>
        </div>

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

        @if ($offerings->isEmpty())
            <div class="alert alert-secondary">Nenhuma turma ofertada para este curso no semestre selecionado.</div>
        @else
            <section class="card border-0 shadow-sm mb-3" aria-label="Grade semanal da turma {{ $classCode }}">
                <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <strong>Turma {{ $classCode }} · {{ $shift }}</strong>
                    <small class="text-muted">Aulas de 50 minutos</small>
                </div>
                <div class="card-body pt-0">
                    @include('partials.weekly-grid')
                </div>
            </section>

            <section class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0"><strong>Disciplinas da turma</strong></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Disciplina</th><th>Professor</th><th>Dias e horários</th><th>Sala</th></tr></thead>
                        <tbody>
                            @foreach ($offerings as $offering)
                                @php
                                    $slots = $offering->slots->sortBy(fn ($s) => [$s->weekday, $s->starts_at]);
                                @endphp
                                <tr>
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
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
