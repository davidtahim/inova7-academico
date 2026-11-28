@extends('layouts.app')

@php
    $ownArea = request()->routeIs('teacher.*');
    $backUrl = $ownArea ? route('teacher.dashboard') : (Gate::allows('access-catalog') ? route('admin.professors.index') : route('dashboard'));
    $selectedLookup = array_flip($selectedIds);
    $shiftShort = ['MANHÃ' => 'Manhã', 'TARDE' => 'Tarde', 'NOITE' => 'Noite', 'INTEGRAL' => 'Integral'];
    $roomShort = [
        'LABORATORIO_INFORMATICA' => 'Lab. informática', 'LABORATORIO_ENGENHARIA' => 'Lab. engenharia', 'SALA_PRATICA' => 'Sala de prática',
        'ESTUDIO_PRODUCAO' => 'Estúdio', 'LABORATORIO_CIENCIAS' => 'Lab. ciências',
    ];
@endphp

@section('title', ($ownArea ? 'Minhas disciplinas' : 'Habilitação — ' . $professor->name) . ' • Inova7 Acadêmico')
@section('page-title', $ownArea ? 'Minhas disciplinas' : 'Habilitação docente')

@section('content')
    <div class="container-fluid py-3 px-0 qualification-page">
        <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-3">
            <div style="max-width: 720px">
                <h2 class="h4 mb-1">{{ $ownArea ? 'Quais disciplinas você pode lecionar?' : 'Habilitação de ' . $professor->name }}</h2>
                <p class="text-muted mb-0">
                    @if ($ownArea)
                        Marque as disciplinas para as quais você está preparado. A coordenação só atribui a você
                        disciplinas marcadas aqui, e a montagem automática de horários usa esta lista.
                    @else
                        Disciplinas que o docente está apto a lecionar (CCG-FOR-02). Só elas são sugeridas na alocação e no resolvedor.
                    @endif
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ $ownArea ? route('teacher.availability') : route('admin.professors.availability', $professor) }}" class="btn btn-outline-primary">
                    {{ $ownArea ? 'Minha disponibilidade' : 'Disponibilidade' }}
                </a>
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Voltar</a>
            </div>
        </div>

        @if (! $isEditable)
            <div class="alert alert-secondary">Somente leitura: apenas a coordenação ou o próprio professor alteram a habilitação.</div>
        @endif

        <form action="{{ route('admin.professors.update-subjects', $professor) }}" method="POST" id="qualificationForm">
            @csrf
            @method('PUT')

            {{-- Seleção atual (ao vivo): o que importa ver sempre. --}}
            <section class="card border-0 shadow-sm mb-3" aria-labelledby="selected-title">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h3 class="h6 mb-0" id="selected-title">{{ $ownArea ? 'Você marcou' : 'Marcadas' }}</h3>
                        <span class="badge bg-success rounded-pill" id="selectedCounter">{{ $selectedCount }}</span>
                        <span class="small text-muted" id="selectedHint">{{ $selectedCount ? '' : 'Nenhuma ainda: use a busca ou abra um curso abaixo.' }}</span>
                    </div>
                    <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0" id="selectedChips" aria-live="polite"></ul>
                </div>
            </section>

            {{-- Busca e filtro --}}
            <div class="qualification-toolbar d-flex flex-wrap gap-2 align-items-center mb-3">
                <label class="visually-hidden" for="subjectSearch">Buscar disciplina</label>
                <input type="search" class="form-control" id="subjectSearch" style="flex: 1 1 280px"
                    placeholder="Buscar por nome ou código (ex.: banco de dados)" autocomplete="off">
                <div class="btn-group" role="group" aria-label="Mostrar">
                    <input type="radio" class="btn-check" name="view" id="viewAll" value="all" checked autocomplete="off">
                    <label class="btn btn-outline-secondary" for="viewAll">Todas</label>
                    <input type="radio" class="btn-check" name="view" id="viewMine" value="mine" autocomplete="off">
                    <label class="btn btn-outline-secondary" for="viewMine">Só as marcadas</label>
                </div>
            </div>
            <p class="small text-muted d-none" id="noResults">Nenhuma disciplina encontrada.</p>

            @forelse ($matrices as $group)
                @php
                    $matrix = $group['matrix'];
                    $hasSelected = collect($group['subject_ids'])->contains(fn ($id) => isset($selectedLookup[$id]));
                @endphp
                <details class="card border-0 shadow-sm mb-2 qualification-group" @if ($hasSelected || ($loop->first && $selectedCount === 0)) open @endif>
                    <summary class="card-header bg-white border-0 d-flex flex-wrap gap-2 align-items-center">
                        <span class="chevron" aria-hidden="true">›</span>
                        <span class="me-auto">
                            <strong>{{ $matrix->course->name }}</strong>
                            <span class="text-muted small">· {{ $matrix->name }}</span>
                        </span>
                        <span class="badge rounded-pill bg-light text-dark border" data-group-counter>0 de {{ count($group['subject_ids']) }}</span>
                    </summary>
                    <div class="card-body p-0">
                        @foreach ($group['periods'] as $period => $links)
                            <div class="qualification-period">
                                <div class="period-head d-flex align-items-center gap-2 px-3 py-2">
                                    <strong class="small text-uppercase text-secondary me-auto">{{ $period }}º período</strong>
                                    @if ($isEditable)
                                        <button type="button" class="btn btn-sm btn-link p-0" data-toggle-period>Marcar todas</button>
                                    @endif
                                </div>
                                <ul class="list-unstyled mb-0">
                                    @foreach ($links as $link)
                                        @php
                                            $subject = $link->subject;
                                            $online = \App\Services\TeachingQualificationCatalog::isFullyOnline($subject);
                                        @endphp
                                        <li class="subject-row" data-search="{{ Str::lower(Str::ascii($subject->name . ' ' . $subject->code)) }}">
                                            <label class="subject-option" for="subject_{{ $link->id }}">
                                                <input class="form-check-input subject-checkbox" type="checkbox"
                                                    name="subject_ids[]" value="{{ $subject->id }}" id="subject_{{ $link->id }}"
                                                    data-name="{{ $subject->name }}"
                                                    @checked(isset($selectedLookup[$subject->id])) @disabled(! $isEditable)>
                                                <span class="subject-text">
                                                    <span class="subject-name">{{ $subject->name }}</span>
                                                    <span class="subject-meta">
                                                        {{ $subject->code ?? 'sem código' }}
                                                        · {{ $online ? '100% EaD' : ($shiftShort[$link->shift] ?? $link->shift) }}
                                                        @if ($link->required_room_type && isset($roomShort[$link->required_room_type]) && ! $online)
                                                            · {{ $roomShort[$link->required_room_type] }}
                                                        @endif
                                                    </span>
                                                </span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </details>
            @empty
                <div class="alert alert-warning">
                    Nenhuma matriz curricular ativa possui disciplinas vinculadas.
                    @can('manage-catalog')
                        Vincule as disciplinas em <a href="{{ route('admin.subjects.index') }}">Disciplinas</a>.
                    @endcan
                </div>
            @endforelse

            @if ($otherSubjects->isNotEmpty())
                <details class="card border-0 shadow-sm mb-2 qualification-group"
                    @if ($matrices->isEmpty() || $otherSubjects->contains(fn ($s) => isset($selectedLookup[$s->id]))) open @endif>
                    <summary class="card-header bg-white border-0 d-flex flex-wrap gap-2 align-items-center">
                        <span class="chevron" aria-hidden="true">›</span>
                        <span class="me-auto"><strong>Outras disciplinas</strong> <span class="text-muted small">· fora das matrizes atuais</span></span>
                        <span class="badge rounded-pill bg-light text-dark border" data-group-counter>0 de {{ $otherSubjects->count() }}</span>
                    </summary>
                    <ul class="list-unstyled mb-0">
                        @foreach ($otherSubjects as $subject)
                            <li class="subject-row" data-search="{{ Str::lower(Str::ascii($subject->name . ' ' . $subject->code)) }}">
                                <label class="subject-option" for="other_subject_{{ $subject->id }}">
                                    <input class="form-check-input subject-checkbox" type="checkbox"
                                        name="subject_ids[]" value="{{ $subject->id }}" id="other_subject_{{ $subject->id }}"
                                        data-name="{{ $subject->name }}"
                                        @checked(isset($selectedLookup[$subject->id])) @disabled(! $isEditable)>
                                    <span class="subject-text">
                                        <span class="subject-name">{{ $subject->name }}</span>
                                        <span class="subject-meta">{{ $subject->code ?? 'sem código' }}</span>
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            {{-- Barra de salvar sempre visível --}}
            @if ($isEditable)
                <div class="save-bar" id="saveBar" role="region" aria-label="Salvar habilitação">
                    <div class="d-flex align-items-center gap-2">
                        <span class="small me-auto" id="saveStatus" aria-live="polite">Tudo salvo.</span>
                        <button type="button" class="btn btn-sm btn-link text-nowrap" id="undoChanges" hidden>Desfazer</button>
                        <button type="submit" class="btn btn-primary text-nowrap" id="saveButton" disabled>Salvar</button>
                    </div>
                </div>
            @endif
        </form>
    </div>

    <style>
        .qualification-group > summary { cursor: pointer; list-style: none; padding: .85rem 1rem; }
        .qualification-group > summary::-webkit-details-marker { display: none; }
        .qualification-group .chevron { display: inline-block; transition: transform .15s; font-size: 1.25rem; line-height: 1; color: var(--bs-secondary-color); }
        .qualification-group[open] .chevron { transform: rotate(90deg); }
        .period-head { background: var(--bs-tertiary-bg); border-top: 1px solid var(--bs-border-color-translucent); }
        .subject-option { display: flex; gap: .75rem; align-items: flex-start; padding: .6rem 1rem; cursor: pointer; border-top: 1px solid var(--bs-border-color-translucent); margin: 0; }
        .subject-option:hover { background: #f6f9fc; }
        .subject-option .form-check-input { margin-top: .2rem; flex-shrink: 0; width: 1.15rem; height: 1.15rem; }
        .subject-option:has(.form-check-input:checked) { background: #eef7f1; }
        .subject-option:has(.form-check-input:focus-visible) { outline: 2px solid #0c3d67; outline-offset: -2px; }
        .subject-text { display: flex; flex-direction: column; min-width: 0; }
        .subject-name { font-weight: 600; }
        .subject-meta { font-size: .8rem; color: var(--bs-secondary-color); }
        .selected-chip { display: inline-flex; align-items: center; gap: .25rem; background: #eef7f1; border: 1px solid #b7dfc4; border-radius: 999px; padding: .15rem .25rem .15rem .7rem; font-size: .85rem; }
        .selected-chip button { border: 0; background: transparent; line-height: 1; padding: .15rem .4rem; border-radius: 999px; color: #0f5132; }
        .selected-chip button:hover, .selected-chip button:focus-visible { background: #d1e7dd; }
        .save-bar { margin-top: 1rem; padding: .6rem 1rem; background: #fff; border: 1px solid var(--bs-border-color); border-radius: .75rem; }
        /* Só flutua no rodapé quando há algo a salvar; sem alterações, fica no fim da lista. */
        .save-bar.is-dirty { position: sticky; bottom: .5rem; z-index: 10; border-color: #f0ad4e; box-shadow: 0 -6px 18px rgba(12, 61, 103, .12); }
    </style>

    <script>
        (function () {
            const form = document.getElementById('qualificationForm');
            const boxes = Array.from(form.querySelectorAll('.subject-checkbox'));
            const initial = new Set(boxes.filter(b => b.checked).map(b => b.value));
            const $ = id => document.getElementById(id);
            let submitting = false;

            const names = {};
            boxes.forEach(b => { names[b.value] = b.dataset.name; });
            const selected = () => new Set(boxes.filter(b => b.checked).map(b => b.value));

            // A mesma disciplina pode aparecer em mais de uma matriz (ex.: SI e ADS): caixas sincronizadas.
            function setSubject(value, checked) {
                boxes.filter(b => b.value === value).forEach(b => { b.checked = checked; });
            }

            function isDirty(current) {
                return current.size !== initial.size || [...current].some(v => !initial.has(v));
            }

            function refresh() {
                const current = selected();
                $('selectedCounter').textContent = current.size;
                $('selectedHint').textContent = current.size ? '' : 'Nenhuma ainda: use a busca ou abra um curso abaixo.';

                const chips = $('selectedChips');
                chips.innerHTML = '';
                [...current].sort((a, b) => names[a].localeCompare(names[b], 'pt-BR')).forEach(value => {
                    const li = document.createElement('li');
                    li.className = 'selected-chip';
                    li.textContent = names[value];
                    @if ($isEditable)
                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.innerHTML = '&times;';
                        remove.setAttribute('aria-label', 'Desmarcar ' + names[value]);
                        remove.addEventListener('click', () => { setSubject(value, false); refresh(); applyFilters(); });
                        li.appendChild(remove);
                    @endif
                    chips.appendChild(li);
                });

                form.querySelectorAll('.qualification-group').forEach(group => {
                    const ids = new Set(Array.from(group.querySelectorAll('.subject-checkbox')).map(b => b.value));
                    const checked = [...ids].filter(id => current.has(id)).length;
                    const counter = group.querySelector('[data-group-counter]');
                    counter.textContent = checked + ' de ' + ids.size;
                    counter.className = 'badge rounded-pill ' + (checked ? 'bg-success' : 'bg-light text-dark border');
                });
                form.querySelectorAll('.qualification-period').forEach(period => {
                    const button = period.querySelector('[data-toggle-period]');
                    if (button) {
                        const visible = Array.from(period.querySelectorAll('.subject-row')).filter(r => !r.hidden).map(r => r.querySelector('input'));
                        button.textContent = visible.length && visible.every(b => b.checked) ? 'Desmarcar todas' : 'Marcar todas';
                    }
                });

                const dirty = isDirty(current);
                if ($('saveBar')) {
                    $('saveBar').classList.toggle('is-dirty', dirty);
                    $('saveButton').disabled = !dirty;
                    $('undoChanges').hidden = !dirty;
                    const added = [...current].filter(v => !initial.has(v)).length;
                    const removed = [...initial].filter(v => !current.has(v)).length;
                    $('saveStatus').innerHTML = dirty
                        ? '<strong>Alterações não salvas:</strong> ' + [added ? added + ' marcada(s)' : '', removed ? removed + ' desmarcada(s)' : ''].filter(Boolean).join(', ') + '.'
                        : 'Tudo salvo. ' + current.size + ' disciplina(s) habilitada(s).';
                }
            }

            const normalize = text => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
            function applyFilters() {
                const term = normalize($('subjectSearch').value);
                const mine = $('viewMine').checked;
                let shown = 0;
                form.querySelectorAll('.subject-row').forEach(row => {
                    const box = row.querySelector('input');
                    row.hidden = (term && !row.dataset.search.includes(term)) || (mine && !box.checked);
                    if (!row.hidden) shown++;
                });
                form.querySelectorAll('.qualification-period').forEach(p => { p.hidden = !p.querySelector('.subject-row:not([hidden])'); });
                form.querySelectorAll('.qualification-group').forEach(g => {
                    const any = !!g.querySelector('.subject-row:not([hidden])');
                    g.hidden = !any;
                    if ((term || mine) && any) g.open = true;
                });
                $('noResults').classList.toggle('d-none', shown > 0);
                refresh();
            }

            boxes.forEach(box => box.addEventListener('change', () => { setSubject(box.value, box.checked); refresh(); }));
            form.querySelectorAll('[data-toggle-period]').forEach(button => button.addEventListener('click', () => {
                const visible = Array.from(button.closest('.qualification-period').querySelectorAll('.subject-row'))
                    .filter(r => !r.hidden).map(r => r.querySelector('input'));
                const target = !visible.every(b => b.checked);
                visible.forEach(b => setSubject(b.value, target));
                refresh();
            }));
            $('subjectSearch').addEventListener('input', applyFilters);
            form.querySelectorAll('input[name="view"]').forEach(r => r.addEventListener('change', applyFilters));
            $('undoChanges')?.addEventListener('click', () => { boxes.forEach(b => { b.checked = initial.has(b.value); }); applyFilters(); });

            // Não perder marcações ao sair da página sem salvar.
            form.addEventListener('submit', () => { submitting = true; });
            window.addEventListener('beforeunload', ev => { if (!submitting && isDirty(selected())) { ev.preventDefault(); ev.returnValue = ''; } });

            refresh();
        })();
    </script>
@endsection
