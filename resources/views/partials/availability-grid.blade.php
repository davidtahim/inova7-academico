{{--
    Grade de disponibilidade docente (CCG-FOR-02), usada na área do professor, na tela da
    coordenação e no perfil. Variáveis: AvailabilityGrid::build() + $professor e $isEditable.

    Edição por "pincel": escolhe-se Preferido / Disponível / Indisponível e clica-se (ou
    arrasta-se) sobre os horários; o cabeçalho do dia e o rótulo do horário preenchem a
    coluna / a linha inteira. Cada mudança é salva logo em seguida, sempre no semestre
    exibido, e o envio pendente é concluído mesmo se a pessoa sair da página.
--}}
@php
    $days = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
    $dayNames = [1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sábado'];
    // Turnos e letras dos blocos, como na grade das turmas (A, B, C…).
    $shifts = [['Manhã', 0, 6], ['Tarde', 6, 5], ['Noite', 11, 4]];
    $states = [
        'preferred' => ['Preferido', '★', 'Prefiro dar aula neste horário'],
        'available' => ['Disponível', '✓', 'Posso dar aula, se precisar'],
        'unavailable' => ['Indisponível', '', 'Não posso'],
    ];
    $gridId = 'ag-' . $professor->id;
@endphp

<div class="availability-component" id="{{ $gridId }}" data-term-id="{{ $term?->id }}"
    data-save-url="{{ route('admin.professors.update-availability', $professor) }}">

    @if ($isEditable && ($copyFromTerm ?? null))
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2" role="status">
            <div class="me-auto">
                <strong>A grade de {{ $term->code }} está vazia.</strong>
                Há uma grade preenchida em {{ $copyFromTerm->code }}. Quer aproveitá-la como ponto de partida?
            </div>
            <form method="POST" action="{{ route('admin.professors.copy-availability', $professor) }}" class="m-0">
                @csrf
                <input type="hidden" name="from_term_id" value="{{ $copyFromTerm->id }}">
                <input type="hidden" name="to_term_id" value="{{ $term->id }}">
                <button type="submit" class="btn btn-sm btn-primary">Copiar de {{ $copyFromTerm->code }}</button>
            </form>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            {{-- Barra: pincel, ações e situação do salvamento --}}
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                @if ($isEditable)
                    <div class="btn-group ag-brushes" role="radiogroup" aria-label="Marcar horários como">
                        @foreach ($states as $value => [$label, $icon, $hint])
                            <input type="radio" class="btn-check" name="{{ $gridId }}-brush" id="{{ $gridId }}-brush-{{ $value }}" value="{{ $value }}" @checked($value === 'preferred') autocomplete="off">
                            <label class="btn btn-outline-secondary ag-brush ag-brush-{{ $value }}" for="{{ $gridId }}-brush-{{ $value }}" title="{{ $hint }}">
                                <span class="ag-swatch ag-{{ $value }}" aria-hidden="true">{{ $icon }}</span> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-danger" data-ag-clear>Limpar tudo</button>
                @endif
                <span class="ms-auto small ag-status" data-ag-status role="status" aria-live="polite">
                    @if ($isEditable)
                        <span class="text-success">● Tudo salvo</span>
                    @endif
                </span>
            </div>
            @if ($isEditable)
                <p class="small text-muted mb-2">
                    Escolha o tipo acima e <strong>clique ou arraste</strong> sobre os horários.
                    Clique no <strong>dia</strong> ou no <strong>horário</strong> para preencher a coluna ou a linha inteira.
                    As mudanças são salvas sozinhas.
                </p>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center mb-0 ag-table" data-ag-table>
                    <caption class="visually-hidden">Disponibilidade semanal de {{ $professor->name }} em {{ $term?->code }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="ag-time-col">Horário</th>
                            @foreach ($days as $num => $day)
                                <th scope="col">
                                    @if ($isEditable)
                                        <button type="button" class="ag-head-btn" data-ag-fill-day="{{ $num }}" title="Preencher toda a {{ $dayNames[$num] }}">{{ $day }}</button>
                                    @else
                                        {{ $day }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach ($shifts as [$shiftName, $offset, $count])
                        <tbody>
                            <tr class="ag-shift-row"><th colspan="7" scope="rowgroup" class="text-start">{{ $shiftName }}</th></tr>
                            @foreach (array_slice($timeSlots, $offset, $count) as $i => $slot)
                                @php $letter = chr(65 + $i); @endphp
                                <tr>
                                    <th scope="row" class="ag-time-col text-start">
                                        @if ($isEditable)
                                            <button type="button" class="ag-head-btn text-start" data-ag-fill-row="{{ $slot['start'] }}" title="Preencher este horário em todos os dias">
                                                <strong>{{ $letter }}</strong> <span class="text-muted">{{ $slot['start'] }}<span class="ag-end">–{{ $slot['end'] }}</span></span>
                                            </button>
                                        @else
                                            <strong>{{ $letter }}</strong> <span class="text-muted">{{ $slot['start'] }}<span class="ag-end">–{{ $slot['end'] }}</span></span>
                                        @endif
                                    </th>
                                    @foreach ($days as $num => $day)
                                        @php $status = $savedAvailabilities[$num][$slot['start'] . ' - ' . $slot['end']] ?? 'unavailable'; @endphp
                                        <td class="p-0">
                                            <button type="button" class="ag-cell ag-{{ $status }}" data-weekday="{{ $num }}" data-start="{{ $slot['start'] }}" data-end="{{ $slot['end'] }}"
                                                data-status="{{ $status }}" aria-label="{{ ucfirst($dayNames[$num]) }}, {{ $slot['start'] }}: {{ $states[$status][0] }}"
                                                @disabled(! $isEditable)>
                                                <span aria-hidden="true">{{ $states[$status][1] }}</span>
                                            </button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>

            <div class="d-flex flex-wrap gap-3 small mt-3" aria-live="polite">
                @foreach ($states as $value => [$label, $icon, $hint])
                    <span><span class="ag-swatch ag-{{ $value }}" aria-hidden="true">{{ $icon }}</span> {{ $label }}: <strong data-ag-count="{{ $value }}">{{ ['preferred' => $preferredCount, 'available' => $availableCount, 'unavailable' => $unavailableCount][$value] }}</strong></span>
                @endforeach
                <span class="text-muted ms-auto">{{ $term ? 'Semestre ' . $term->code : '' }}</span>
            </div>
        </div>
    </div>
</div>

<style>
    .ag-table { table-layout: fixed; }
    .ag-table > :not(caption) > * > * { padding-top: .15rem; padding-bottom: .15rem; }
    .ag-table th, .ag-table td { border-color: #e3e8ee; }
    .ag-time-col { width: 9.5rem; font-size: .8rem; font-weight: 500; background: #fff; }
    .ag-table thead th { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; background: #f8fafc; }
    .ag-shift-row th { background: #eef2f6 !important; font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #52606d; padding: .25rem .6rem; }
    .ag-head-btn { border: 0; background: transparent; width: 100%; padding: .25rem; border-radius: .35rem; font: inherit; color: inherit; text-transform: inherit; }
    .ag-head-btn:hover, .ag-head-btn:focus-visible { background: #e7eef6; }
    .ag-cell { display: block; width: 100%; height: 2.1rem; border: 0; font-weight: 700; font-size: 1rem; transition: background-color .12s; touch-action: none; user-select: none; }
    .ag-cell:disabled { cursor: default; opacity: 1; }
    .ag-cell:not(:disabled) { cursor: pointer; }
    .ag-cell:not(:disabled):hover { box-shadow: inset 0 0 0 2px rgba(12, 61, 103, .35); }
    .ag-cell:focus-visible { outline: 3px solid #0c3d67; outline-offset: -3px; position: relative; }
    .ag-preferred { background: #198754; color: #fff; }
    .ag-available { background: #9fd3b3; color: #0f5132; }
    .ag-unavailable { background: #f8f9fa; color: #adb5bd; }
    .ag-swatch { display: inline-flex; align-items: center; justify-content: center; width: 1.1rem; height: 1.1rem; border-radius: .25rem; font-size: .7rem; border: 1px solid rgba(0, 0, 0, .1); vertical-align: -3px; }
    .ag-brush { font-size: .875rem; }
    .btn-check:checked + .ag-brush { background: #0c3d67; border-color: #0c3d67; color: #fff; }
    @media (max-width: 575.98px) {
        .ag-time-col { width: 4.4rem; white-space: nowrap; font-size: .72rem; }
        .ag-time-col .ag-head-btn { padding-left: .15rem; }
        .ag-end { display: none; }
        .ag-table thead th { font-size: .7rem; letter-spacing: 0; }
        .ag-head-btn { padding: .2rem 0; }
    }
    .ag-status .spinner-border { width: .8rem; height: .8rem; border-width: .15em; }
</style>

@if ($isEditable)
    <script>
        (function () {
            const root = document.getElementById(@json($gridId));
            const cells = Array.from(root.querySelectorAll('.ag-cell'));
            const status = root.querySelector('[data-ag-status]');
            const labels = @json(collect($states)->map(fn ($s) => $s[0]));
            const icons = @json(collect($states)->map(fn ($s) => $s[1]));
            const dayNames = @json($dayNames);
            const csrf = @json(csrf_token());
            let dirty = false, saving = false, failed = false, timer = null, dragging = false;

            const brush = () => root.querySelector('input[name="' + root.id + '-brush"]:checked').value;

            function paint(cell, value) {
                if (cell.dataset.status === value) return false;
                cell.dataset.status = value;
                cell.className = 'ag-cell ag-' + value;
                cell.firstElementChild.textContent = icons[value];
                cell.setAttribute('aria-label', dayNames[cell.dataset.weekday].replace(/^./, c => c.toUpperCase()) + ', ' + cell.dataset.start + ': ' + labels[value]);
                return true;
            }

            function changed(any) {
                if (!any) return;
                counts();
                dirty = true;
                setStatus('pending');
                clearTimeout(timer);
                timer = setTimeout(save, 400); // salva logo; agrupa só cliques muito seguidos
            }

            function counts() {
                ['preferred', 'available', 'unavailable'].forEach(v => {
                    root.querySelector('[data-ag-count="' + v + '"]').textContent = cells.filter(c => c.dataset.status === v).length;
                });
            }

            function setStatus(state, detail) {
                const html = {
                    pending: '<span class="text-warning-emphasis">● Alterações a salvar…</span>',
                    saving: '<span class="text-primary"><span class="spinner-border" aria-hidden="true"></span> Salvando…</span>',
                    saved: '<span class="text-success">● Salvo às ' + (detail || '') + '</span>',
                    error: '<span class="text-danger">● Não foi possível salvar.</span> <button type="button" class="btn btn-sm btn-link p-0 align-baseline" data-ag-retry>Tentar de novo</button>',
                }[state];
                status.innerHTML = html;
                status.querySelector('[data-ag-retry]')?.addEventListener('click', save);
            }

            function payload() {
                return JSON.stringify({
                    academic_term_id: Number(root.dataset.termId) || null,
                    availability_slots: cells.filter(c => c.dataset.status !== 'unavailable').map(c => ({
                        weekday: Number(c.dataset.weekday), starts_at: c.dataset.start, ends_at: c.dataset.end, preference: c.dataset.status,
                    })),
                });
            }

            async function save() {
                clearTimeout(timer);
                if (saving) { timer = setTimeout(save, 300); return; }
                saving = true; dirty = false;
                setStatus('saving');
                try {
                    // POST em vez de PUT: algumas hospedagens bloqueiam PUT; a rota aceita os dois.
                    const response = await fetch(root.dataset.saveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: payload(),
                    });
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    const data = await response.json();
                    failed = false;
                    setStatus(dirty ? 'pending' : 'saved', data.saved_at);
                } catch (e) {
                    failed = true; dirty = true;
                    setStatus('error');
                } finally {
                    saving = false;
                    if (dirty && !failed) timer = setTimeout(save, 300);
                }
            }

            // Clique e arraste (mouse, caneta ou dedo).
            root.querySelector('[data-ag-table]').addEventListener('pointerdown', ev => {
                const cell = ev.target.closest('.ag-cell');
                if (!cell || cell.disabled) return;
                ev.preventDefault();
                dragging = true;
                changed(paint(cell, brush()));
            });
            root.querySelector('[data-ag-table]').addEventListener('pointerover', ev => {
                if (!dragging) return;
                const cell = ev.target.closest('.ag-cell');
                if (cell) changed(paint(cell, brush()));
            });
            // Toque: o pointerover não dispara ao arrastar o dedo; usa a posição.
            root.querySelector('[data-ag-table]').addEventListener('pointermove', ev => {
                if (!dragging || ev.pointerType === 'mouse') return;
                const cell = document.elementFromPoint(ev.clientX, ev.clientY)?.closest('.ag-cell');
                if (cell && root.contains(cell)) changed(paint(cell, brush()));
            });
            window.addEventListener('pointerup', () => { dragging = false; });

            // Teclado: Enter/Espaço aplica o pincel (o clique do botão já faz isso sem pointerdown).
            cells.forEach(cell => cell.addEventListener('keydown', ev => {
                if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); changed(paint(cell, brush())); }
            }));

            // Preencher coluna / linha: se tudo já está no pincel, volta para Indisponível.
            function fill(list) {
                const value = list.every(c => c.dataset.status === brush()) ? 'unavailable' : brush();
                let any = false;
                list.forEach(c => { any = paint(c, value) || any; });
                changed(any);
            }
            root.querySelectorAll('[data-ag-fill-day]').forEach(b => b.addEventListener('click', () => fill(cells.filter(c => c.dataset.weekday === b.dataset.agFillDay))));
            root.querySelectorAll('[data-ag-fill-row]').forEach(b => b.addEventListener('click', () => fill(cells.filter(c => c.dataset.start === b.dataset.agFillRow))));
            root.querySelector('[data-ag-clear]').addEventListener('click', () => {
                if (confirm('Marcar todos os horários como indisponíveis?')) {
                    let any = false;
                    cells.forEach(c => { any = paint(c, 'unavailable') || any; });
                    changed(any);
                }
            });

            // Ao sair da página: envia o que estiver pendente (keepalive sobrevive à navegação)
            // e avisa se o último envio falhou.
            window.addEventListener('pagehide', () => {
                if (dirty) {
                    fetch(root.dataset.saveUrl, {
                        method: 'POST', keepalive: true,
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: payload(),
                    });
                }
            });
            window.addEventListener('beforeunload', ev => { if (failed) { ev.preventDefault(); ev.returnValue = ''; } });
        })();
    </script>
@endif
