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
    // Linguagem do professor: "posso" é a marcação principal; "prefiro" destaca os melhores
    // horários; "não posso" apaga. (No banco: available / preferred / unavailable.)
    $states = [
        'available' => ['Posso dar aula', '✓', 'Você pode ter aula neste horário. É a marcação principal.'],
        'preferred' => ['Prefiro', '★', 'Seus melhores horários: a coordenação e a montagem automática usam estes primeiro.'],
        'unavailable' => ['Não posso', '', 'Apaga a marcação: nunca haverá aula sua neste horário.'],
    ];
    // Atalhos: turno inteiro de segunda a sexta (e sábado de manhã).
    $presets = [
        ['Noites, seg a sex', array_column(array_slice($timeSlots, 11, 4), 'start'), [1, 2, 3, 4, 5]],
        ['Manhãs, seg a sex', array_column(array_slice($timeSlots, 0, 6), 'start'), [1, 2, 3, 4, 5]],
        ['Tardes, seg a sex', array_column(array_slice($timeSlots, 6, 5), 'start'), [1, 2, 3, 4, 5]],
        ['Sábado de manhã', array_column(array_slice($timeSlots, 0, 6), 'start'), [6]],
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
            @if ($isEditable)
                {{-- Passo 1: o que marcar. Passo 2: onde (grade ou atalhos). --}}
                <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                    <span class="ag-step">1</span><strong>O que você quer marcar?</strong>
                    <span class="ms-auto small ag-status" data-ag-status role="status" aria-live="polite"><span class="text-success">● Tudo salvo</span></span>
                </div>
                <div class="row g-2 mb-3 ag-options" role="radiogroup" aria-label="Marcar horários como">
                    @foreach ($states as $value => [$label, $icon, $hint])
                        <div class="col-md-4">
                            <input type="radio" class="btn-check" name="{{ $gridId }}-brush" id="{{ $gridId }}-brush-{{ $value }}" value="{{ $value }}" @checked($value === 'available') autocomplete="off">
                            <label class="ag-option ag-option-{{ $value }}" for="{{ $gridId }}-brush-{{ $value }}">
                                <span class="ag-swatch ag-{{ $value }}" aria-hidden="true">{{ $icon ?: '–' }}</span>
                                <span>
                                    <span class="d-block fw-semibold">{{ $label }}</span>
                                    <span class="d-block small text-muted">{{ $hint }}</span>
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                    <span class="ag-step">2</span><strong>Onde?</strong>
                    <span class="small text-muted">Clique ou arraste nos horários. O dia e o horário no cabeçalho preenchem a coluna ou a linha. Ou use um atalho:</span>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($presets as [$presetLabel, $starts, $presetDays])
                        <button type="button" class="btn btn-sm btn-outline-primary" data-ag-preset data-starts="{{ implode(',', $starts) }}" data-days="{{ implode(',', $presetDays) }}">{{ $presetLabel }}</button>
                    @endforeach
                    <button type="button" class="btn btn-sm btn-outline-danger ms-md-auto" data-ag-clear>Limpar tudo</button>
                </div>
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
                                            <button type="button" class="ag-cell ag-{{ $status }}" data-weekday="{{ $num }}" data-start="{{ $slot['start'] }}" data-end="{{ $slot['end'] }}" data-shift="{{ $shiftName }}" data-letter="{{ $letter }}"
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

            {{-- Resumo em texto: confere o que foi marcado sem ler a grade célula a célula. --}}
            <div class="ag-summary mt-3" aria-live="polite">
                <div class="d-flex flex-wrap gap-3 small mb-1">
                    <span><span class="ag-swatch ag-available" aria-hidden="true">✓</span> Posso: <strong data-ag-count="available">{{ $availableCount }}</strong></span>
                    <span><span class="ag-swatch ag-preferred" aria-hidden="true">★</span> Prefiro: <strong data-ag-count="preferred">{{ $preferredCount }}</strong></span>
                    <span class="text-muted">Demais horários: não posso</span>
                    <span class="text-muted ms-auto">{{ $term ? 'Semestre ' . $term->code : '' }}</span>
                </div>
                @php
                    // Mesmo resumo que o JavaScript atualiza ao editar (também vale para somente leitura).
                    $summaryLines = [];
                    foreach ($days as $num => $day) {
                        $parts = [];
                        foreach ($shifts as [$shiftName, $offset, $count]) {
                            $letters = [];
                            foreach (array_slice($timeSlots, $offset, $count) as $i => $slot) {
                                $st = $savedAvailabilities[$num][$slot['start'] . ' - ' . $slot['end']] ?? 'unavailable';
                                if ($st !== 'unavailable') {
                                    $letters[] = chr(65 + $i) . ($st === 'preferred' ? '★' : '');
                                }
                            }
                            if ($letters) {
                                $parts[] = $shiftName . ' ' . implode(' ', $letters);
                            }
                        }
                        if ($parts) {
                            $summaryLines[] = [$day, implode(' · ', $parts)];
                        }
                    }
                @endphp
                <ul class="list-unstyled small mb-0" data-ag-summary>
                    @forelse ($summaryLines as [$day, $text])
                        <li><strong>{{ $day }}</strong> — {{ $text }}</li>
                    @empty
                        <li class="text-warning-emphasis">Nenhum horário marcado ainda: para a coordenação, {{ $isEditable ? 'você não pode' : 'o professor não pode' }} dar aula em nenhum horário.</li>
                    @endforelse
                </ul>
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
    .ag-preferred { background: #0f6b40; color: #fff; }
    .ag-available { background: #9fd3b3; color: #0f5132; }
    .ag-step { display: inline-grid; place-items: center; width: 1.5rem; height: 1.5rem; border-radius: 50%; background: #0c3d67; color: #fff; font-size: .8rem; font-weight: 700; flex-shrink: 0; }
    .ag-option { display: flex; gap: .75rem; align-items: flex-start; height: 100%; padding: .7rem .85rem; border: 2px solid #dee2e6; border-radius: .6rem; cursor: pointer; background: #fff; transition: border-color .12s, background-color .12s; }
    .ag-option:hover { border-color: #9fb7cc; }
    .btn-check:checked + .ag-option { border-color: #0c3d67; background: #f1f6fb; box-shadow: 0 0 0 3px rgba(12, 61, 103, .12); }
    .btn-check:focus-visible + .ag-option { outline: 3px solid #0c3d67; outline-offset: 2px; }
    .ag-option .ag-swatch { width: 1.6rem; height: 1.6rem; font-size: .9rem; margin-top: .1rem; flex-shrink: 0; }
    .ag-unavailable { background: #f8f9fa; color: #adb5bd; }
    .ag-swatch { display: inline-flex; align-items: center; justify-content: center; width: 1.1rem; height: 1.1rem; border-radius: .25rem; font-size: .7rem; border: 1px solid rgba(0, 0, 0, .1); vertical-align: -3px; }
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
                ['preferred', 'available'].forEach(v => {
                    const el = root.querySelector('[data-ag-count="' + v + '"]');
                    if (el) el.textContent = cells.filter(c => c.dataset.status === v).length;
                });
                summary();
            }

            // Resumo em texto por dia: "Seg — Noite A★ B · Tarde C".
            function summary() {
                const list = root.querySelector('[data-ag-summary]');
                if (!list) return;
                const short = { 1: 'Seg', 2: 'Ter', 3: 'Qua', 4: 'Qui', 5: 'Sex', 6: 'Sáb' };
                const lines = [];
                for (let d = 1; d <= 6; d++) {
                    const marked = cells.filter(c => c.dataset.weekday === String(d) && c.dataset.status !== 'unavailable');
                    if (!marked.length) continue;
                    const byShift = {};
                    marked.forEach(c => { (byShift[c.dataset.shift] ||= []).push(c.dataset.letter + (c.dataset.status === 'preferred' ? '★' : '')); });
                    lines.push('<li><strong>' + short[d] + '</strong> — ' + Object.entries(byShift).map(([shift, letters]) => shift + ' ' + letters.join(' ')).join(' · ') + '</li>');
                }
                list.innerHTML = lines.length ? lines.join('') : '<li class="text-warning-emphasis">Nenhum horário marcado ainda: para a coordenação, você não pode dar aula em nenhum horário.</li>';
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
            // Atalhos de turno: aplicam a opção escolhida (ou desfazem, se já estiver toda assim).
            root.querySelectorAll('[data-ag-preset]').forEach(b => b.addEventListener('click', () => {
                const starts = b.dataset.starts.split(','), days = b.dataset.days.split(',');
                fill(cells.filter(c => starts.includes(c.dataset.start) && days.includes(c.dataset.weekday)));
            }));
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
            summary();
        })();
    </script>
@endif
