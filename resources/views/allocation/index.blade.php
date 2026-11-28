@extends('layouts.app')

@section('page-title', 'Montagem de horários')

@php
    $feedback = session('allocation');
    $copyReport = session('copy_report');
    $modal = [];
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
                                $current = $inputs[$offering->id];
                                $mine = ($feedback['id'] ?? null) === $offering->id;
                                $failed = $mine && ! ($feedback['saved'] ?? false);
                                $link = $offering->matrixDiscipline;
                                $practical = \App\Services\LabRules::offeringRequiresLab($offering);
                                $isDistance = $offering->isDistance();
                                $expected = $offering->classesToSchedule();
                                $placements = $failed ? (array) old('placements', []) : $current['placements'];
                                $placed = array_sum(array_map('count', $placements));
                                $profId = (int) ($failed ? old('professor_id', $current['professor_id']) : $current['professor_id']);
                                $professor = $professors->firstWhere('id', $profId) ?? $offering->assignments->first()?->professor;
                                $room = $failed ? old('room', $current['room']) : $current['room'];
                                $qualifiedIds = collect($qualified)->filter(fn ($ids) => in_array($offering->subject_id, $ids, true))->keys()->all();
                                $eligible = \App\Services\LabRules::eligibleRooms($offering, $rooms);

                                // Ocupação da turma pelas outras disciplinas (para a mini grade do modal).
                                $turmaBusy = [];
                                foreach ($offerings as $other) {
                                    if ($other->id === $offering->id) continue;
                                    foreach ($inputs[$other->id]['placements'] as $d => $ls) {
                                        foreach ($ls as $l) {
                                            $turmaBusy[$d . '|' . $l] = $other->subject->short_label ?? $other->subject->name;
                                        }
                                    }
                                }

                                $summary = collect($placements)->map(fn ($ls, $d) => $weekdays[$d] . ' ' . implode('', $ls))->implode(' · ');
                                $status = $isDistance ? ['EaD', 'info'] : ($expected > 0 && $placed === $expected ? ['Completa', 'success'] : ($placed === 0 ? ['Sem horário', 'secondary'] : ['Faltam ' . max(0, $expected - $placed) . ' aula(s)', 'warning']));
                                if (! $isDistance && $placed > $expected && $expected > 0) $status = ['Aulas a mais', 'danger'];

                                $modal[$offering->id] = [
                                    'id' => $offering->id,
                                    'action' => route('allocation.update', $offering),
                                    'name' => $offering->subject->name,
                                    'code' => $offering->subject->code,
                                    'class' => $offering->class_code,
                                    'period' => $offering->period,
                                    'shift' => $offering->shift,
                                    'expected' => $expected,
                                    'practical' => $practical,
                                    'distance' => $isDistance,
                                    'labelType' => $link?->required_room_type ? (\App\Models\Room::TYPES[$link->required_room_type] ?? $link->required_room_type) : null,
                                    'professor' => $profId ?: null,
                                    'placements' => (object) $placements,
                                    'room' => $room,
                                    'students' => $failed ? old('student_count', $offering->student_count) : $offering->student_count,
                                    'directed' => (bool) ($failed ? old('directed_study') : $offering->directed_study),
                                    'hours' => (float) ($failed ? old('weekly_hours', $offering->weekly_hours) : $offering->weekly_hours),
                                    'regular' => (float) ($link?->weekly_hours ?: 2),
                                    'qualified' => $qualifiedIds,
                                    'rooms' => $eligible->map(fn ($r) => ['code' => $r->code, 'label' => $r->code . ($r->isLab() && $r->computers ? ' · ' . $r->computers . ($r->isComputerLab() ? ' PCs' : ' vagas') : '')])->values(),
                                    'turmaBusy' => (object) $turmaBusy,
                                    'errors' => $failed ? ($feedback['errors'] ?? []) : [],
                                    'warnings' => $mine ? ($feedback['warnings'] ?? []) : [],
                                    'open' => $failed,
                                ];
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

    {{-- Edição de uma disciplina da turma (um único modal, preenchido pelo JavaScript). --}}
    <div class="modal fade" id="allocModal" tabindex="-1" aria-labelledby="allocTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <form class="modal-content" method="POST" id="allocForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="per_day" value="1">
                <div class="modal-header align-items-start">
                    <div>
                        <h2 class="modal-title h5 mb-1" id="allocTitle"></h2>
                        <div class="small text-muted" id="allocMeta"></div>
                        <div class="d-flex flex-wrap gap-1 mt-1" id="allocBadges"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger small d-none" id="allocErrors" role="alert"></div>
                    <div class="alert alert-warning small d-none" id="allocWarnings"></div>

                    {{-- 1. Professor --}}
                    <section class="mb-4" aria-labelledby="sec-prof">
                        <h3 class="alloc-sec" id="sec-prof"><span>1</span> Professor</h3>
                        <select name="professor_id" id="allocProfessor" class="form-select"></select>
                        <div class="form-text" id="allocProfHint"></div>
                    </section>

                    {{-- 2. Horário --}}
                    <section class="mb-4" aria-labelledby="sec-time">
                        <div class="d-flex flex-wrap align-items-baseline gap-2">
                            <h3 class="alloc-sec me-auto" id="sec-time"><span>2</span> Horário</h3>
                            <span class="badge rounded-pill" id="allocCounter" aria-live="polite"></span>
                            <button type="button" class="btn btn-sm btn-link p-0" id="allocClear">Limpar horário</button>
                        </div>
                        <p class="small text-muted mb-2">Clique nos blocos de cada dia. Aulas seguidas formam um bloco (ex.: AB).</p>
                        <div class="table-responsive">
                            <table class="table table-bordered text-center align-middle mb-1 alloc-grid">
                                <thead><tr><th scope="col"></th>@foreach ($weekdays as $d => $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead>
                                <tbody id="allocGrid"></tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap gap-3 small text-muted" aria-hidden="true">
                            <span><span class="alloc-key is-on"></span> escolhido</span>
                            <span><span class="alloc-key is-turma"></span> turma ocupada</span>
                            <span><span class="alloc-key is-prof"></span> professor em outra turma</span>
                            <span><span class="alloc-key is-off"></span> professor indisponível</span>
                            <span>★ horário preferido do professor</span>
                        </div>
                    </section>

                    {{-- 3. Sala --}}
                    <section class="mb-4" aria-labelledby="sec-room">
                        <h3 class="alloc-sec" id="sec-room"><span>3</span> Sala</h3>
                        <select name="room" id="allocRoom" class="form-select"></select>
                        <div class="form-text" id="allocRoomHint"></div>
                    </section>

                    {{-- 4. Carga docente --}}
                    <details class="alloc-details" id="allocLoad">
                        <summary class="alloc-sec"><span>4</span> Alunos e carga docente</summary>
                        <div class="row g-3 mt-1">
                            <div class="col-sm-4">
                                <label class="form-label small" for="allocStudents">Alunos</label>
                                <input type="number" min="0" max="500" name="student_count" id="allocStudents" class="form-control">
                            </div>
                            <div class="col-sm-4 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="directed_study" value="1" id="allocDirected">
                                    <label class="form-check-label small" for="allocDirected">Estudo Dirigido</label>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label small" for="allocHours">Hora-aula docente</label>
                                <input type="number" min="0" max="20" step="0.5" name="weekly_hours" id="allocHours" class="form-control">
                            </div>
                            <div class="col-12 form-text mt-0">Com até {{ $directedStudyMax }} alunos a turma vira Estudo Dirigido (hora-aula 1; 0 sem alunos).</div>
                        </div>
                    </details>
                    <div id="allocPlacementInputs"></div>
                </div>
                <div class="modal-footer">
                    <span class="small text-muted me-auto">O sistema confere choques e disponibilidade ao salvar.</span>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>

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
        .alloc-sec { font-size: .95rem; font-weight: 600; margin-bottom: .5rem; display: flex; align-items: center; gap: .5rem; }
        .alloc-sec > span { display: inline-grid; place-items: center; width: 1.4rem; height: 1.4rem; border-radius: 50%; background: #0c3d67; color: #fff; font-size: .75rem; }
        .alloc-details > summary { cursor: pointer; list-style: none; }
        .alloc-details > summary::-webkit-details-marker { display: none; }
        .alloc-grid { table-layout: fixed; }
        .alloc-grid th:first-child { width: 4.6rem; }
        @media (max-width: 575.98px) {
            .alloc-grid th:first-child { width: 3.4rem; }
            .alloc-grid th, .alloc-cell { font-size: .68rem; }
            .alloc-grid th { padding: .3rem .1rem; }
        }
        .alloc-grid th { font-size: .75rem; color: #52606d; font-weight: 600; }
        .alloc-grid td { padding: 0; }
        .alloc-cell { width: 100%; height: 2.4rem; border: 0; background: #fff; font-size: .8rem; position: relative; }
        .alloc-cell:hover:not(:disabled) { background: #eaf2fb; }
        .alloc-cell:focus-visible { outline: 3px solid #0c3d67; outline-offset: -3px; }
        .alloc-cell.is-on { background: #1264a3; color: #fff; font-weight: 700; }
        .alloc-cell.is-turma { background: repeating-linear-gradient(45deg, #f1f3f5, #f1f3f5 6px, #e9ecef 6px, #e9ecef 12px); color: #6c757d; cursor: not-allowed; }
        .alloc-cell.is-prof:not(.is-on) { box-shadow: inset 0 0 0 2px #dc3545; color: #b02a37; }
        .alloc-cell.is-off:not(.is-on) { background: #f8f9fa; color: #adb5bd; text-decoration: line-through; }
        .alloc-cell.is-on.is-prof, .alloc-cell.is-on.is-off { background: #b02a37; }
        .alloc-key { display: inline-block; width: .9rem; height: .9rem; border-radius: 3px; vertical-align: -2px; border: 1px solid #ced4da; }
        .alloc-key.is-on { background: #1264a3; border-color: #1264a3; }
        .alloc-key.is-turma { background: repeating-linear-gradient(45deg, #f1f3f5, #f1f3f5 3px, #dee2e6 3px, #dee2e6 6px); }
        .alloc-key.is-prof { box-shadow: inset 0 0 0 2px #dc3545; }
        .alloc-key.is-off { background: #f8f9fa; }
    </style>

    @php
        $jsProfessors = $professors->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values();
        $jsShifts = [];
        foreach (['MANHÃ', 'TARDE', 'NOITE', 'INTEGRAL'] as $shiftName) {
            $jsShifts[$shiftName] = \App\Services\TimeBlocks::forShift($shiftName);
        }
    @endphp
    <script>
        (function () {
            const data = @json($modal);
            const professors = @json($jsProfessors);
            const busy = @json($professorBusy);
            const availability = @json($professorAvailability);
            const shifts = @json($jsShifts);
            const weekdays = @json($weekdays);
            const edMax = {{ (int) $directedStudyMax }};
            const $ = id => document.getElementById(id);
            const modalEl = $('allocModal'), form = $('allocForm');
            let current = null, chosen = {};

            const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

            function open(id) {
                current = data[id];
                const o = current;
                form.action = o.action;
                $('allocTitle').textContent = o.name;
                $('allocMeta').textContent = `${o.code} · Turma ${o.class} · ${o.period}º período · ${o.shift.toLowerCase()} · ${o.distance ? 'a distância' : o.expected + ' aula(s) por semana'}`;
                $('allocBadges').innerHTML = (o.practical ? `<span class="badge bg-warning-subtle text-warning-emphasis">${esc(o.labelType || 'Laboratório de Informática')}</span>` : '')
                    + (o.directed ? '<span class="badge bg-info-subtle text-info-emphasis">Estudo Dirigido</span>' : '')
                    + (o.distance ? '<span class="badge bg-info-subtle text-info-emphasis">EaD — não precisa de horário</span>' : '');

                const box = (el, items, icon) => { el.classList.toggle('d-none', !items.length); el.innerHTML = items.map(i => `<div>${icon} ${esc(i)}</div>`).join(''); };
                box($('allocErrors'), o.errors, '✗');
                box($('allocWarnings'), o.warnings, '⚠');

                // Professor: habilitados primeiro.
                const q = new Set(o.qualified);
                const opt = p => `<option value="${p.id}" ${p.id === o.professor ? 'selected' : ''}>${esc(p.name)}</option>`;
                $('allocProfessor').innerHTML = '<option value="">— A definir —</option>'
                    + `<optgroup label="Habilitados na disciplina">${professors.filter(p => q.has(p.id)).map(opt).join('')}</optgroup>`
                    + `<optgroup label="Outros professores">${professors.filter(p => !q.has(p.id)).map(opt).join('')}</optgroup>`;

                // Sala: já filtrada pelas regras de laboratório.
                const rooms = o.rooms.map(r => `<option value="${esc(r.code)}" ${r.code === o.room ? 'selected' : ''}>${esc(r.label)}</option>`).join('');
                const stale = o.room && !o.rooms.some(r => r.code === o.room) ? `<option value="${esc(o.room)}" selected>${esc(o.room)} (não permitida: troque)</option>` : '';
                $('allocRoom').innerHTML = `<option value="">${o.practical ? '— Laboratório —' : '— Sala —'}</option>` + rooms + stale;
                $('allocRoomHint').textContent = o.practical
                    ? (o.rooms.length ? 'Só laboratórios aptos: tipo exigido, vagas para a turma e softwares.' : 'Nenhum laboratório apto (tipo, vagas ou softwares): confira o cadastro de laboratórios.')
                    : 'Disciplina sem carga prática: laboratórios ficam de fora (Art. 8º III).';

                $('allocStudents').value = o.students ?? '';
                $('allocDirected').checked = o.directed;
                $('allocHours').value = o.hours;
                $('allocLoad').open = o.directed || o.errors.length > 0 && o.students !== null;

                chosen = {};
                Object.entries(o.placements || {}).forEach(([d, ls]) => ls.forEach(l => { chosen[d + '|' + l] = true; }));
                renderGrid();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }

            function renderGrid() {
                const o = current, letters = shifts[o.shift] || {};
                const prof = Number($('allocProfessor').value) || null;
                const profBusy = (prof && busy[prof]) || {}, grid = prof ? availability[prof] : null;
                let html = '';
                Object.entries(letters).forEach(([letter, slot]) => {
                    html += `<tr><th scope="row" class="text-nowrap small">${letter} <span class="fw-normal text-muted">${slot.start}</span></th>`;
                    Object.keys(weekdays).forEach(d => {
                        const key = d + '|' + letter, at = d + '|' + slot.start;
                        const turma = o.turmaBusy[key];
                        const other = (profBusy[at] || []).filter(b => b.offering !== o.id);
                        const pref = grid ? (grid[at] || 'unavailable') : null;
                        const cls = ['alloc-cell'];
                        let label = `${weekdays[d]} ${letter} (${slot.start})`, mark = '';
                        if (chosen[key]) { cls.push('is-on'); mark = letter; }
                        if (turma) { cls.push('is-turma'); label += `: turma já tem ${turma}`; mark = turma; }
                        if (other.length) { cls.push('is-prof'); label += `: professor em ${other.map(b => b.label).join(', ')}`; }
                        if (pref === 'unavailable') { cls.push('is-off'); label += ': professor indisponível'; }
                        if (pref === 'preferred' && !chosen[key] && !turma) mark = '★';
                        html += `<td><button type="button" class="${cls.join(' ')}" data-key="${key}" ${turma ? 'disabled' : ''} aria-pressed="${!!chosen[key]}" title="${esc(label)}" aria-label="${esc(label)}">${esc(mark)}</button></td>`;
                    });
                    html += '</tr>';
                });
                $('allocGrid').innerHTML = html;
                counter();
                const conflicts = Object.keys(chosen).filter(k => (profBusy[k.split('|')[0] + '|' + (letters[k.split('|')[1]] || {}).start] || []).some(b => b.offering !== o.id)).length;
                $('allocProfHint').textContent = !prof ? 'Sem professor, a turma fica "a definir".'
                    : (!grid ? 'Este professor ainda não preencheu a disponibilidade.' : 'Indisponíveis aparecem riscados; ★ = horário preferido.')
                    + (conflicts ? ` Atenção: ${conflicts} horário(s) escolhido(s) chocam com outra turma dele.` : '');
            }

            function counter() {
                const n = Object.keys(chosen).length, exp = current.expected, el = $('allocCounter');
                el.textContent = current.distance ? `${n} aula(s) — EaD` : `${n} de ${exp} aulas`;
                el.className = 'badge rounded-pill ' + (current.distance ? 'text-bg-info' : n === exp ? 'text-bg-success' : n > exp ? 'text-bg-danger' : 'text-bg-warning');
            }

            $('allocGrid').addEventListener('click', ev => {
                const cell = ev.target.closest('.alloc-cell');
                if (!cell || cell.disabled) return;
                chosen[cell.dataset.key] ? delete chosen[cell.dataset.key] : (chosen[cell.dataset.key] = true);
                renderGrid();
                $('allocGrid').querySelector(`[data-key="${cell.dataset.key}"]`)?.focus();
            });
            $('allocClear').addEventListener('click', () => { chosen = {}; renderGrid(); });
            $('allocProfessor').addEventListener('change', renderGrid);

            // Regra da planilha: até N alunos vira Estudo Dirigido (hora-aula 1; 0 sem alunos).
            $('allocStudents').addEventListener('change', () => {
                if ($('allocStudents').value === '') return;
                const n = parseInt($('allocStudents').value, 10), directed = n <= edMax;
                $('allocDirected').checked = directed;
                $('allocHours').value = directed ? (n === 0 ? 0 : 1) : current.regular;
            });

            form.addEventListener('submit', () => {
                $('allocPlacementInputs').innerHTML = Object.keys(chosen).map(k => {
                    const [d, l] = k.split('|');
                    return `<input type="hidden" name="placements[${d}][]" value="${l}">`;
                }).join('');
            });

            document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => open(b.dataset.edit)));
            // Erro ao salvar: reabre o modal da disciplina com as mensagens e o que foi preenchido.
            const failed = Object.values(data).find(o => o.open);
            if (failed) window.addEventListener('load', () => open(failed.id));
        })();
    </script>
@endsection
