@extends('layouts.app')

@section('page-title', 'Habilitação docente - ' . $professor->name)

@php
    $backUrl = Gate::allows('access-catalog') ? route('admin.professors.index') : route('dashboard');
    $selectedLookup = array_flip($selectedIds);
@endphp

@section('content')
    <div class="container py-4">
        <div class="page-head mb-4 d-flex flex-wrap gap-2 align-items-start justify-content-between">
            <div>
                <h2 class="mb-1">Habilitação docente</h2>
                <p class="mb-0"><strong>Professor:</strong> {{ $professor->name }}</p>
                <small class="text-muted">
                    Marque as disciplinas que o docente está apto a lecionar (formulário CCG-FOR-02).
                    Somente disciplinas habilitadas são sugeridas na alocação de turmas.
                </small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Voltar</a>
                <a href="{{ route('admin.professors.availability', $professor) }}" class="btn btn-outline-primary">📅 Disponibilidade</a>
            </div>
        </div>

        @if (! $isEditable)
            <div class="alert alert-secondary">Visualização somente leitura.</div>
        @endif

        <form action="{{ route('admin.professors.update-subjects', $professor) }}" method="POST" id="qualificationForm">
            @csrf
            @method('PUT')

            <div class="row g-3">
                {{-- Catálogo --}}
                <div class="col-lg-8 order-2 order-lg-1">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <input type="search" class="form-control" id="subjectSearch"
                                placeholder="Buscar por nome ou código da disciplina…" autocomplete="off">
                        </div>
                    </div>

                    @forelse ($matrices as $group)
                        @php
                            $matrix = $group['matrix'];
                        @endphp
                        <details class="card border-0 shadow-sm mb-3 qualification-group" open>
                            <summary class="card-header bg-light border-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                                <span>
                                    <strong>{{ $matrix->course->name }}</strong>
                                    <small class="text-muted">— {{ $matrix->name }} ({{ $matrix->code }})</small>
                                </span>
                                <span class="badge bg-success" data-group-counter>0 / {{ count($group['subject_ids']) }}</span>
                            </summary>
                            <div class="card-body p-0">
                                @foreach ($group['periods'] as $period => $links)
                                    <div class="qualification-period">
                                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-white">
                                            <strong class="small text-uppercase text-secondary">{{ $period }}º período</strong>
                                            @if ($isEditable)
                                                <button type="button" class="btn btn-link btn-sm p-0" data-toggle-period>
                                                    Marcar / desmarcar período
                                                </button>
                                            @endif
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <tbody>
                                                    @foreach ($links as $link)
                                                        @php
                                                            $subject = $link->subject;
                                                            $online = \App\Services\TeachingQualificationCatalog::isFullyOnline($subject);
                                                        @endphp
                                                        <tr class="subject-row"
                                                            data-search="{{ Str::lower(Str::ascii($subject->name . ' ' . $subject->code)) }}">
                                                            <td style="width: 2.5rem;" class="ps-3">
                                                                <input class="form-check-input subject-checkbox" type="checkbox"
                                                                    name="subject_ids[]" value="{{ $subject->id }}"
                                                                    id="subject_{{ $link->id }}"
                                                                    @checked(isset($selectedLookup[$subject->id]))
                                                                    @disabled(! $isEditable)>
                                                            </td>
                                                            <td>
                                                                <label for="subject_{{ $link->id }}" class="mb-0 w-100">
                                                                    <strong>{{ $subject->name }}</strong>
                                                                    <br><small class="text-muted">{{ $subject->code ?? 'sem código' }}</small>
                                                                </label>
                                                            </td>
                                                            <td class="text-end small text-nowrap pe-3">
                                                                @if ($online)
                                                                    <span class="badge bg-info-subtle text-info-emphasis">100% EaD</span>
                                                                @else
                                                                    <span class="badge bg-light text-dark border">{{ $link->weekly_hours }} aula(s)/sem</span>
                                                                @endif
                                                                <span class="badge bg-light text-dark border">{{ $link->shift }}</span>
                                                                @if ($link->required_room_type && ! $online)
                                                                    <span class="badge bg-warning-subtle text-warning-emphasis">
                                                                        {{ $roomTypes[$link->required_room_type] ?? $link->required_room_type }}
                                                                    </span>
                                                                @endif
                                                                @if ($link->allows_shared_class)
                                                                    <span class="badge bg-success-subtle text-success-emphasis">Compartilhada</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <div class="alert alert-warning">
                            Nenhuma matriz curricular ativa possui disciplinas vinculadas.
                            @can('manage-catalog')
                                Vincule as disciplinas em <a href="{{ route('admin.matrices.index') }}">Matrizes curriculares</a>.
                            @endcan
                        </div>
                    @endforelse

                    @if ($otherSubjects->isNotEmpty())
                        <details class="card border-0 shadow-sm mb-3 qualification-group"
                            @if ($matrices->isEmpty() || $otherSubjects->contains(fn ($s) => isset($selectedLookup[$s->id]))) open @endif>
                            <summary class="card-header bg-light border-0 d-flex align-items-center justify-content-between">
                                <span>
                                    <strong>Outras disciplinas</strong>
                                    <small class="text-muted">— sem vínculo com matriz ativa</small>
                                </span>
                                <span class="badge bg-secondary" data-group-counter>0 / {{ $otherSubjects->count() }}</span>
                            </summary>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <tbody>
                                        @foreach ($otherSubjects as $subject)
                                            <tr class="subject-row"
                                                data-search="{{ Str::lower(Str::ascii($subject->name . ' ' . $subject->code)) }}">
                                                <td style="width: 2.5rem;" class="ps-3">
                                                    <input class="form-check-input subject-checkbox" type="checkbox"
                                                        name="subject_ids[]" value="{{ $subject->id }}"
                                                        id="other_subject_{{ $subject->id }}"
                                                        @checked(isset($selectedLookup[$subject->id]))
                                                        @disabled(! $isEditable)>
                                                </td>
                                                <td>
                                                    <label for="other_subject_{{ $subject->id }}" class="mb-0 w-100">
                                                        <strong>{{ $subject->name }}</strong>
                                                        <br><small class="text-muted">{{ $subject->code ?? 'sem código' }}</small>
                                                    </label>
                                                </td>
                                                <td class="text-end small text-muted pe-3">
                                                    @if ($subject->total_hours)
                                                        {{ $subject->total_hours }}h
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @endif
                </div>

                {{-- Resumo lateral --}}
                <div class="col-lg-4 order-1 order-lg-2">
                    <div class="card border-0 shadow-sm qualification-summary">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0">Resumo</h5>
                        </div>
                        <div class="card-body">
                            <div class="stat-row">
                                <span class="text-muted">Disciplinas no catálogo</span>
                                <strong>{{ $totalSubjects }}</strong>
                            </div>
                            <div class="stat-row">
                                <span class="text-muted">Habilitado em</span>
                                <span class="badge bg-success fs-6" id="selectedCounter">{{ $selectedCount }}</span>
                            </div>
                            <div class="stat-row">
                                <span class="text-muted">Não habilitado</span>
                                <span class="badge bg-secondary fs-6" id="unselectedCounter">{{ $unselectedCount }}</span>
                            </div>

                            <hr>
                            <h6 class="small text-uppercase text-secondary">Habilitações salvas</h6>
                            <ul class="list-unstyled small mb-0">
                                @forelse ($selectedSubjects as $subject)
                                    <li>✓ {{ $subject->name }}</li>
                                @empty
                                    <li class="text-muted">Nenhuma disciplina habilitada.</li>
                                @endforelse
                            </ul>
                        </div>
                        @if ($isEditable)
                            <div class="card-footer bg-white border-0 pb-3">
                                <div class="small text-warning mb-2 d-none" id="dirtyWarning">Há alterações não salvas.</div>
                                <button type="submit" class="btn btn-primary w-100">Salvar habilitação</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>

    <style>
        .stat-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #e9ecef; }
        .stat-row:last-of-type { border-bottom: none; }
        .qualification-group > summary { cursor: pointer; list-style: none; }
        .qualification-group > summary::-webkit-details-marker { display: none; }
        .subject-row label { cursor: pointer; }
        @media (min-width: 992px) { .qualification-summary { position: sticky; top: 1rem; } }
    </style>

    <script>
        (function () {
            const form = document.getElementById('qualificationForm');
            const boxes = Array.from(form.querySelectorAll('.subject-checkbox'));
            const total = {{ (int) $totalSubjects }};
            const markDirty = () => document.getElementById('dirtyWarning')?.classList.remove('d-none');

            // A mesma disciplina pode aparecer em mais de uma matriz (ex.: SI e ADS): mantém as caixas sincronizadas.
            function sync(source) {
                boxes.filter(b => b.value === source.value && b !== source).forEach(b => { b.checked = source.checked; });
            }

            function refresh() {
                const selected = new Set(boxes.filter(b => b.checked).map(b => b.value));
                document.getElementById('selectedCounter').textContent = selected.size;
                document.getElementById('unselectedCounter').textContent = Math.max(0, total - selected.size);

                form.querySelectorAll('.qualification-group').forEach(group => {
                    const ids = new Set(Array.from(group.querySelectorAll('.subject-checkbox')).map(b => b.value));
                    const checked = [...ids].filter(id => selected.has(id)).length;
                    const counter = group.querySelector('[data-group-counter]');
                    if (counter) counter.textContent = `${checked} / ${ids.size}`;
                });
            }

            boxes.forEach(box => box.addEventListener('change', () => { sync(box); refresh(); markDirty(); }));

            form.querySelectorAll('[data-toggle-period]').forEach(button => button.addEventListener('click', () => {
                const periodBoxes = Array.from(button.closest('.qualification-period').querySelectorAll('.subject-checkbox'))
                    .filter(b => b.closest('tr').style.display !== 'none');
                const target = !periodBoxes.every(b => b.checked);
                periodBoxes.forEach(b => { b.checked = target; sync(b); });
                refresh();
                markDirty();
            }));

            const normalize = text => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
            document.getElementById('subjectSearch').addEventListener('input', event => {
                const term = normalize(event.target.value);
                form.querySelectorAll('.subject-row').forEach(row => {
                    row.style.display = !term || row.dataset.search.includes(term) ? '' : 'none';
                });
                if (term) form.querySelectorAll('.qualification-group').forEach(g => { g.open = true; });
            });

            refresh();
        })();
    </script>
@endsection
