@extends('layouts.app')

@section('title', 'Disciplinas • Inova7 Acadêmico')
@section('page-title', $matrix ? 'Disciplinas — ' . $matrix->name : 'Disciplinas')

@section('content')
    @php
        $shiftShort = ['MANHÃ' => 'Manhã', 'TARDE' => 'Tarde', 'NOITE' => 'Noite', 'INTEGRAL' => 'Integral'];
        $roomShort = [
            'SALA_COMUM' => 'Sala comum', 'LABORATORIO_INFORMATICA' => 'Lab. informática', 'LABORATORIO_ENGENHARIA' => 'Lab. engenharia',
            'SALA_PRATICA' => 'Sala de prática', 'ESTUDIO_PRODUCAO' => 'Estúdio', 'LABORATORIO_CIENCIAS' => 'Lab. ciências',
        ];
        $tabUrl = fn ($value) => route('admin.subjects.index', array_filter(['matriz' => $value, 'q' => $query]));
    @endphp

    <div class="container-fluid py-3 px-0 subjects-page">
        <div class="page-head mb-3">
            <div>
                <h2>Disciplinas</h2>
                <p class="mb-0">Escolha uma matriz para montar a grade curricular por período, ou veja o catálogo completo.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if ($matrix)
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#linkModal" data-link-new>
                        + Adicionar à {{ $matrix->name }}
                    </button>
                @endif
                <a href="{{ route('admin.subjects.create', $matrix ? ['matriz' => $matrix->id] : []) }}"
                    class="btn {{ $matrix ? 'btn-outline-primary' : 'btn-primary' }}">Nova disciplina</a>
            </div>
        </div>

        {{-- Visões: uma aba por matriz + catálogo. --}}
        {{-- No celular, as visões viram um seletor compacto. --}}
        <div class="d-md-none mb-3">
            <label class="form-label small fw-semibold" for="subjects-view">Visão</label>
            <select id="subjects-view" class="form-select" onchange="window.location = this.value">
                <optgroup label="Grade por matriz">
                    @foreach ($matrices as $m)
                        <option value="{{ $tabUrl($m->id) }}" @selected((string) $view === (string) $m->id)>{{ $m->name }} — {{ $m->course?->name }} ({{ $m->disciplines_for_offering_count }})</option>
                    @endforeach
                </optgroup>
                <optgroup label="Catálogo">
                    <option value="{{ $tabUrl('todas') }}" @selected($view === 'todas')>Todas as disciplinas ({{ $totalSubjects }})</option>
                    <option value="{{ $tabUrl('sem') }}" @selected($view === 'sem')>Sem matriz ({{ $unlinkedCount }})</option>
                </optgroup>
            </select>
        </div>

        <nav class="subjects-tabs mb-3 d-none d-md-block" aria-label="Visões de disciplinas">
            <ul class="nav nav-pills flex-wrap gap-2">
                @foreach ($matrices as $m)
                    <li>
                        <a class="nav-link {{ (string) $view === (string) $m->id ? 'active' : '' }}" href="{{ $tabUrl($m->id) }}"
                            @if ((string) $view === (string) $m->id) aria-current="page" @endif title="{{ $m->code }}">
                            <span class="d-block fw-semibold">{{ $m->name }}</span>
                            <small class="d-block">{{ $m->course?->name ?? 'sem curso' }} · {{ $m->disciplines_for_offering_count }}</small>
                        </a>
                    </li>
                @endforeach
                <li class="ms-md-2 ps-md-2 border-start">
                    <a class="nav-link {{ $view === 'todas' ? 'active' : '' }}" href="{{ $tabUrl('todas') }}">
                        <span class="d-block fw-semibold">Catálogo</span>
                        <small class="d-block">todas · {{ $totalSubjects }}</small>
                    </a>
                </li>
                <li>
                    <a class="nav-link {{ $view === 'sem' ? 'active' : '' }}" href="{{ $tabUrl('sem') }}">
                        <span class="d-block fw-semibold">Sem matriz</span>
                        <small class="d-block">fora de grade · {{ $unlinkedCount }}</small>
                    </a>
                </li>
            </ul>
        </nav>

        <form method="GET" action="{{ route('admin.subjects.index') }}" class="d-flex gap-2 mb-3" role="search">
            <input type="hidden" name="matriz" value="{{ $view }}">
            <label class="visually-hidden" for="subjects-q">Buscar disciplina</label>
            <input type="search" id="subjects-q" name="q" class="form-control" value="{{ $query }}"
                placeholder="{{ $matrix ? 'Buscar nesta matriz por nome, código ou sigla' : 'Buscar por nome, código ou sigla' }}">
            <button type="submit" class="btn btn-outline-primary">Buscar</button>
            @if ($query !== '')
                <a href="{{ route('admin.subjects.index', ['matriz' => $view]) }}" class="btn btn-outline-secondary">Limpar</a>
            @endif
        </form>

        @if ($matrix)
            {{-- ===================== Visão por matriz ===================== --}}
            <section class="card border-0 shadow-sm mb-3" aria-label="Resumo da matriz">
                <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
                    <div class="me-auto">
                        <strong>{{ $matrix->name }}</strong>
                        <span class="text-muted small">· {{ $matrix->course?->name ?? 'sem curso' }} · {{ $matrix->code }}</span>
                        @if ($matrix->status)
                            <span class="badge {{ $matrix->status === 'Atual' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">{{ $matrix->status }}</span>
                        @endif
                    </div>
                    <span class="small"><strong>{{ $matrixTotals['subjects'] }}</strong> disciplinas</span>
                    <span class="small"><strong>{{ $matrixTotals['periods'] }}</strong> períodos</span>
                    <span class="small"><strong>{{ $matrixTotals['classes'] }}</strong> aulas/semana no total</span>
                    <a href="{{ route('admin.matrices.edit', $matrix) }}" class="btn btn-sm btn-outline-secondary">Dados da matriz</a>
                </div>
            </section>

            @if ($linksByPeriod->isEmpty())
                <div class="alert alert-info">
                    {{ $query !== '' ? 'Nenhuma disciplina desta matriz corresponde à busca.' : 'Esta matriz ainda não tem disciplinas. Use "Adicionar" para montar a grade.' }}
                </div>
            @else
                @if ($linksByPeriod->count() > 2)
                    <nav class="d-flex flex-wrap gap-1 mb-2 small" aria-label="Ir para o período">
                        <span class="text-muted me-1 align-self-center">Ir para:</span>
                        @foreach ($linksByPeriod->keys() as $period)
                            <a href="#periodo-{{ $period }}" class="btn btn-sm btn-light border">{{ $period }}º</a>
                        @endforeach
                    </nav>
                @endif

                @foreach ($linksByPeriod as $period => $links)
                    <section class="card border-0 shadow-sm mb-3 period-card" id="periodo-{{ $period }}" aria-labelledby="periodo-{{ $period }}-t">
                        <header class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                            <h3 class="h6 mb-0" id="periodo-{{ $period }}-t">{{ $period }}º período</h3>
                            <span class="text-muted small">{{ $links->count() }} {{ $links->count() === 1 ? 'disciplina' : 'disciplinas' }} · {{ $links->sum('weekly_hours') }} aulas/semana</span>
                            <button type="button" class="btn btn-sm btn-link ms-auto" data-bs-toggle="modal" data-bs-target="#linkModal" data-link-new data-period="{{ $period }}">+ Adicionar neste período</button>
                        </header>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 subjects-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Disciplina</th>
                                        <th scope="col" class="text-center">Aulas/sem</th>
                                        <th scope="col">Turno</th>
                                        <th scope="col">Tipo</th>
                                        <th scope="col">Sala exigida</th>
                                        <th scope="col" class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($links as $link)
                                        @php($subject = $link->subject)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $subject?->name ?? '—' }}</div>
                                                <div class="small text-muted">
                                                    {{ $subject?->code }}
                                                    @if ($subject?->abbreviation) · <span title="Sigla na grade">{{ $subject->abbreviation }}</span>@endif
                                                    @if ($link->total_hours) · {{ $link->total_hours }}h @endif
                                                    @if ($link->modality) · {{ $link->modality }} @endif
                                                </div>
                                            </td>
                                            <td class="text-center"><span class="badge bg-primary-subtle text-primary-emphasis fs-6">{{ $link->weekly_hours }}</span></td>
                                            <td>{{ $shiftShort[$link->shift] ?? $link->shift }}</td>
                                            <td>
                                                {{ $offeringTypes[$link->offering_type] ?? $link->offering_type }}
                                                @if ($link->allows_shared_class)<span class="badge bg-success-subtle text-success-emphasis ms-1">compartilhada</span>@endif
                                            </td>
                                            <td>{{ $link->required_room_type ? ($roomShort[$link->required_room_type] ?? $link->required_room_type) : '—' }}</td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#linkModal"
                                                    data-link-edit="{{ route('admin.matrices.disciplines.update', [$matrix, $link]) }}"
                                                    data-subject-name="{{ $subject?->name }}" data-period="{{ $link->period }}" data-weekly="{{ $link->weekly_hours }}"
                                                    data-shift="{{ $link->shift }}" data-type="{{ $link->offering_type }}" data-room="{{ $link->required_room_type }}"
                                                    data-consecutive="{{ $link->max_consecutive_classes }}" data-shared="{{ $link->allows_shared_class ? 1 : 0 }}">
                                                    Ajustar
                                                </button>
                                                @if ($subject)
                                                    <a href="{{ route('admin.subjects.edit', $subject) }}" class="btn btn-sm btn-outline-secondary" title="Nome, código, sigla e ementa">Disciplina</a>
                                                @endif
                                                <form action="{{ route('admin.matrices.disciplines.destroy', [$matrix, $link]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Tirar {{ addslashes($subject?->name) }} do {{ $period }}º período da {{ $matrix->name }}? A disciplina continua no catálogo.')">Tirar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            @endif

            {{-- Um único modal para adicionar e ajustar vínculos (em vez de um por linha). --}}
            <div class="modal fade" id="linkModal" tabindex="-1" aria-labelledby="linkModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form class="modal-content" method="POST" id="linkForm" action="{{ route('admin.matrices.disciplines.store', $matrix) }}"
                        data-store="{{ route('admin.matrices.disciplines.store', $matrix) }}">
                        @csrf
                        <input type="hidden" name="_method" value="POST" id="linkMethod">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="linkModalTitle">Adicionar disciplina à {{ $matrix->name }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12" id="subjectField">
                                    <label class="form-label" for="linkSubject">Disciplina *</label>
                                    <select name="subject_id" id="linkSubject" class="form-select">
                                        <option value="">Selecione…</option>
                                        <optgroup label="Ainda não estão nesta matriz">
                                            @foreach ($availableSubjects->reject(fn ($s) => in_array($s->id, $linkedIds, true)) as $s)
                                                <option value="{{ $s->id }}" data-practice="{{ (int) $s->practice_hours }}" @selected((int) old('subject_id', session('link_subject')) === $s->id)>{{ $s->name }} — {{ $s->code }}{{ $s->practice_hours ? ' · prática' : '' }}</option>
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="Já estão nesta matriz (outro período)">
                                            @foreach ($availableSubjects->filter(fn ($s) => in_array($s->id, $linkedIds, true)) as $s)
                                                <option value="{{ $s->id }}" data-practice="{{ (int) $s->practice_hours }}">{{ $s->name }} — {{ $s->code }}{{ $s->practice_hours ? ' · prática' : '' }}</option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                    <div class="form-text">Não encontrou? <a href="{{ route('admin.subjects.create', ['matriz' => $matrix->id]) }}">Cadastre uma nova disciplina</a> e volte para vinculá-la.</div>
                                </div>
                                <div class="col-12" id="subjectName" hidden><strong></strong></div>
                                <div class="col-md-4">
                                    <label class="form-label" for="linkPeriod">Período *</label>
                                    <select name="period" id="linkPeriod" class="form-select" required>
                                        @for ($p = 1; $p <= 8; $p++)
                                            <option value="{{ $p }}">{{ $p }}º período</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="linkWeekly">Aulas por semana *</label>
                                    <input type="number" name="weekly_hours" id="linkWeekly" class="form-control" min="0" max="30" value="2" required>
                                    <div class="form-text">40h e 60h = 2; 80h = 3.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="linkShift">Turno *</label>
                                    <select name="shift" id="linkShift" class="form-select" required>
                                        @foreach ($shifts as $key => $label)
                                            <option value="{{ $key }}" @selected($key === 'NOITE')>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="linkType">Tipo de oferta *</label>
                                    <select name="offering_type" id="linkType" class="form-select" required>
                                        @foreach ($offeringTypes as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="linkRoom">Sala exigida</label>
                                    <select name="required_room_type" id="linkRoom" class="form-select">
                                        <option value="">Qualquer sala</option>
                                        @foreach ($roomTypes as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="linkConsecutive">Máx. aulas seguidas</label>
                                    <input type="number" name="max_consecutive_classes" id="linkConsecutive" class="form-control" value="2" min="1" max="6">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allows_shared_class" id="linkShared" value="1">
                                        <label class="form-check-label" for="linkShared">Permite aula compartilhada entre turmas</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="linkSubmit">Adicionar</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                (function () {
                    const modal = document.getElementById('linkModal');
                    const form = document.getElementById('linkForm');
                    const $ = id => document.getElementById(id);
                    modal.addEventListener('show.bs.modal', event => {
                        const t = event.relatedTarget || {};
                        const d = t.dataset || {};
                        const editing = !!d.linkEdit;
                        form.action = editing ? d.linkEdit : form.dataset.store;
                        $('linkMethod').value = editing ? 'PUT' : 'POST';
                        $('subjectField').hidden = editing;
                        $('linkSubject').required = !editing;
                        $('subjectName').hidden = !editing;
                        $('subjectName').querySelector('strong').textContent = d.subjectName || '';
                        $('linkModalTitle').textContent = editing ? 'Ajustar vínculo na {{ $matrix->name }}' : 'Adicionar disciplina à {{ $matrix->name }}';
                        $('linkSubmit').textContent = editing ? 'Salvar' : 'Adicionar';
                        $('linkPeriod').value = d.period || '1';
                        if (editing) {
                            $('linkWeekly').value = d.weekly; $('linkShift').value = d.shift; $('linkType').value = d.type;
                            $('linkRoom').value = d.room || ''; $('linkConsecutive').value = d.consecutive || 2; $('linkShared').checked = d.shared === '1';
                        } else {
                            form.reset(); $('linkPeriod').value = d.period || '1';
                            @if (session('link_subject')) $('linkSubject').value = @json((string) session('link_subject')); @endif
                        }
                    });
                    modal.addEventListener('shown.bs.modal', () => ($('subjectField').hidden ? $('linkWeekly') : $('linkSubject')).focus());
                    // Art. 8º III: disciplina com carga prática → sugere laboratório de informática.
                    $('linkSubject').addEventListener('change', () => {
                        const practice = Number($('linkSubject').selectedOptions[0]?.dataset.practice || 0);
                        if (practice > 0) $('linkRoom').value = 'LABORATORIO_INFORMATICA';
                        else if ($('linkRoom').value === 'LABORATORIO_INFORMATICA') $('linkRoom').value = '';
                    });
                    @if (session('link_subject') || ($errors->any() && old('subject_id')))
                        // Disciplina recém-cadastrada (ou erro de validação): reabre o modal para concluir o vínculo.
                        window.addEventListener('load', () => document.querySelector('[data-link-new]')?.click());
                    @endif
                })();
            </script>
        @else
            {{-- ===================== Catálogo ===================== --}}
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 subjects-table">
                        <thead>
                            <tr>
                                <th scope="col">Disciplina</th>
                                <th scope="col">Sigla</th>
                                <th scope="col">Onde está na grade</th>
                                <th scope="col" class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($subjects as $subject)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $subject->name }}</div>
                                        <div class="small text-muted">{{ $subject->code }}</div>
                                    </td>
                                    <td>{{ $subject->abbreviation ?: '—' }}</td>
                                    <td>
                                        @forelse ($subject->offeringLinks as $link)
                                            <a href="{{ route('admin.subjects.index', ['matriz' => $link->curriculum_matrix_id]) }}#periodo-{{ $link->period }}"
                                                class="badge rounded-pill bg-light text-dark border text-decoration-none me-1 mb-1"
                                                title="{{ $link->weekly_hours }} aulas/semana">
                                                {{ $link->curriculumMatrix?->name }} · {{ $link->period }}º
                                            </a>
                                        @empty
                                            <span class="small text-warning-emphasis">Fora de todas as matrizes</span>
                                        @endforelse
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if ($matrices->isNotEmpty())
                                            <button type="button" class="btn btn-sm {{ $subject->offeringLinks->isEmpty() ? 'btn-primary' : 'btn-outline-primary' }}"
                                                data-bs-toggle="modal" data-bs-target="#catalogLinkModal"
                                                data-subject-id="{{ $subject->id }}" data-subject-name="{{ $subject->name }}" data-practice="{{ (int) $subject->practice_hours }}">Adicionar à matriz</button>
                                        @endif
                                        <a href="{{ route('admin.subjects.edit', $subject) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                        <form action="{{ route('admin.subjects.destroy', $subject) }}" method="POST" class="d-inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('{{ $subject->offeringLinks->isNotEmpty() ? 'Esta disciplina está em ' . $subject->offeringLinks->count() . ' matriz(es). ' : '' }}Excluir {{ addslashes($subject->name) }} do catálogo?')">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        {{ $query !== '' ? 'Nenhuma disciplina corresponde à busca.' : ($view === 'sem' ? 'Todas as disciplinas estão em alguma matriz.' : 'Nenhuma disciplina cadastrada.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{-- Pôr uma disciplina do catálogo numa matriz (escolhe matriz e período aqui). --}}
            @if ($matrices->isNotEmpty())
                <div class="modal fade" id="catalogLinkModal" tabindex="-1" aria-labelledby="catalogLinkTitle" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <form class="modal-content" method="POST" id="catalogLinkForm"
                            data-action-template="{{ route('admin.matrices.disciplines.store', ['matrix' => '__ID__']) }}">
                            @csrf
                            <input type="hidden" name="subject_id" id="catalogLinkSubject">
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="catalogLinkTitle">Adicionar à matriz</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-3"><strong id="catalogLinkName"></strong></p>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label" for="catalogLinkMatrix">Matriz (curso) *</label>
                                        <select id="catalogLinkMatrix" class="form-select" required>
                                            <option value="">Selecione…</option>
                                            @foreach ($matrices->groupBy(fn ($m) => $m->course?->name ?? 'Sem curso') as $courseName => $group)
                                                <optgroup label="{{ $courseName }}">
                                                    @foreach ($group as $m)
                                                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->code }})</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="catalogLinkPeriod">Período *</label>
                                        <select name="period" id="catalogLinkPeriod" class="form-select" required>
                                            @for ($p = 1; $p <= 8; $p++)
                                                <option value="{{ $p }}">{{ $p }}º período</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="catalogLinkWeekly">Aulas por semana *</label>
                                        <input type="number" name="weekly_hours" id="catalogLinkWeekly" class="form-control" min="0" max="30" value="2" required>
                                        <div class="form-text">40h e 60h = 2; 80h = 3.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="catalogLinkShift">Turno *</label>
                                        <select name="shift" id="catalogLinkShift" class="form-select" required>
                                            @foreach ($shifts as $key => $label)
                                                <option value="{{ $key }}" @selected($key === 'NOITE')>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="catalogLinkType">Tipo de oferta *</label>
                                        <select name="offering_type" id="catalogLinkType" class="form-select" required>
                                            @foreach ($offeringTypes as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="catalogLinkRoom">Sala exigida</label>
                                        <select name="required_room_type" id="catalogLinkRoom" class="form-select">
                                            <option value="">Qualquer sala</option>
                                            @foreach ($roomTypes as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Adicionar</button>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                    (function () {
                        const modal = document.getElementById('catalogLinkModal');
                        const form = document.getElementById('catalogLinkForm');
                        const matrix = document.getElementById('catalogLinkMatrix');
                        modal.addEventListener('show.bs.modal', ev => {
                            const d = ev.relatedTarget?.dataset || {};
                            document.getElementById('catalogLinkSubject').value = d.subjectId || '';
                            document.getElementById('catalogLinkName').textContent = d.subjectName || '';
                            // Carga prática → laboratório de informática (Art. 8º III).
                            document.getElementById('catalogLinkRoom').value = Number(d.practice || 0) > 0 ? 'LABORATORIO_INFORMATICA' : '';
                        });
                        // A rota de gravação é da matriz escolhida.
                        form.addEventListener('submit', ev => {
                            if (!matrix.value) { ev.preventDefault(); matrix.focus(); return; }
                            form.action = form.dataset.actionTemplate.replace('__ID__', matrix.value);
                        });
                    })();
                </script>
            @endif
        @endif
    </div>

    <style>
        .subjects-tabs ul { padding: 0; margin: 0; }
        .subjects-tabs li { margin: 0; }
        .subjects-tabs .nav-link { line-height: 1.2; border: 1px solid var(--bs-border-color); background: #fff; padding: .4rem .75rem; }
        .subjects-tabs .nav-link small { font-size: .75rem; }
        .subjects-tabs .nav-link small { opacity: .75; }
        .subjects-tabs .nav-link.active small { opacity: .9; }
        .period-card { scroll-margin-top: 1rem; }
        .subjects-table th { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); font-weight: 600; }
    </style>
@endsection
