@extends('layouts.app')
@section('title', 'Planejamento • Inova7 Acadêmico')
@section('page-title', 'Planejamento Semestral')
@section('content')
    <section class="page-head">
        <div>
            <h2>Alocação docente</h2>
            <p>Ofertas, modalidade, professor, dia, horário e sala.</p>
        </div><span class="badge badge-soft-yellow p-2">{{ $conflicts->count() }} conflito(s)</span>
    </section>
    <form class="filter-card row g-2 mb-3" method="get">
        <div class="col-md-3"><label class="form-label small">Semestre</label><select class="form-select" name="term">
                @foreach ($terms as $item)
                    <option value="{{ $item->id }}" @selected($term?->id === $item->id)>{{ $item->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4"><label class="form-label small">Curso</label><select class="form-select" name="course">
                <option value="">Todos</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected(request('course') == $course->id)>{{ $course->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label small">Turno</label><select class="form-select" name="shift">
                <option value="">Todos</option>
                @foreach (['MANHÃ', 'TARDE', 'NOITE'] as $shift)
                    <option @selected(request('shift') === $shift)>{{ $shift }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Filtrar</button></div>
    </form>
    <article class="card card-soft">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Turma</th>
                        <th>Disciplina</th>
                        <th>Modalidade</th>
                        <th>Professor</th>
                        <th>Horário</th>
                        <th>Sala</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offerings as $offering)
                        <tr>
                            <td><strong>{{ $offering->class_code }}</strong>
                                <div class="small text-secondary">{{ $offering->course->code }} • {{ $offering->shift }}
                                </div>
                            </td>
                            <td>{{ $offering->subject->name }}</td>
                            <td><span class="badge badge-soft-blue">{{ $offering->modality }}</span></td>
                            <td>
                                @if ($offering->assignments->isNotEmpty())
                                    <div class="fw-semibold">
                                        {{ $offering->assignments->first()->professor?->name ?? 'Pendente' }}
                                    </div>
                                @elseif ($offering->suggested_professor)
                                    <div class="fw-semibold">Professor Sugerido</div>
                                    <div class="small text-primary">{{ $offering->suggested_professor_name }}</div>
                                    <div class="small mt-1">
                                        <span class="badge badge-soft-green">{{ $offering->compatibility_status }}</span>
                                    </div>
                                @else
                                    <span class="text-muted">Pendente</span>
                                @endif
                            </td>
                            <td>
                                @foreach ($offering->slots as $slot)
                                    <div>{{ ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][$slot->weekday] ?? '?' }}
                                        {{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}</div>
                                @endforeach
                            </td>
                            <td>{{ $offering->slots->first()?->room ?? '—' }}</td>
                            <td><span
                                    class="badge {{ $offering->status === 'confirmed' ? 'badge-soft-green' : 'badge-soft-yellow' }}">{{ $offering->status }}</span>
                            </td>
                    </tr>@empty<tr>
                            <td colspan="7" class="empty">Nenhuma oferta encontrada. Execute as migrations e o seeder.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
@endsection
