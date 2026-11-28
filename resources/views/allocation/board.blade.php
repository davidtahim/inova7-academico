@extends('layouts.app')

@section('page-title', 'Grade global')

@section('content')
    <div class="container-fluid py-3 px-0 board-page">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-2">
            <div>
                <h2 class="h4 mb-1">Grade global — {{ $term->code ?? 'sem semestre' }}</h2>
                <p class="text-muted small mb-0">
                    Arraste as aulas (ou clique na aula e depois no horário). Cada movimento é validado:
                    choques são recusados, avisos aparecem abaixo. Ctrl+Z desfaz, Ctrl+Y refaz.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="form-label small fw-semibold mb-1" for="b-course">Curso</label>
                    <select id="b-course" class="form-select form-select-sm">
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-semibold mb-1" for="b-shift">Turno</label>
                    <select id="b-shift" class="form-select form-select-sm">
                        <option value="NOITE">Noite</option>
                        <option value="MANHÃ">Manhã</option>
                        <option value="TARDE">Tarde</option>
                    </select>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="b-undo" disabled title="Desfazer (Ctrl+Z)">↶ Desfazer</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="b-redo" disabled title="Refazer (Ctrl+Y)">↷ Refazer</button>
                <a href="{{ route('allocation.index') }}" class="btn btn-sm btn-outline-primary">Formulário de montagem</a>
            </div>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body py-2 d-flex align-items-center gap-3">
                        <div>
                            <div class="small text-muted text-uppercase">Pontos</div>
                            <div class="fs-3 fw-bold" id="b-score">—</div>
                        </div>
                        <div class="small">
                            <div><span class="badge bg-danger-subtle text-danger-emphasis" id="b-hard">— rígidas</span></div>
                            <div class="mt-1"><span class="badge bg-warning-subtle text-warning-emphasis" id="b-soft">— flexíveis</span></div>
                        </div>
                        <span class="badge ms-auto" id="b-status" role="status">Carregando…</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body py-2 small" id="b-breakdown" aria-live="polite">—</div>
                </div>
            </div>
        </div>

        <div id="b-toast" class="board-toast" aria-live="assertive"></div>

        <div class="row g-2">
            <div class="col-xl-9">
                <div class="card border-0 shadow-sm">
                    <div class="board-scroll" id="b-grid">Carregando…</div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card border-0 shadow-sm board-pending">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <strong>A alocar</strong>
                        <small class="text-muted">solte aqui para tirar da grade</small>
                    </div>
                    <div class="card-body pt-0" id="b-pending" data-drop="pending"></div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .board-scroll { overflow: auto; max-height: calc(100vh - 280px); }
        .board-table { border-collapse: separate; border-spacing: 0; font-size: .75rem; min-width: 100%; }
        .board-table th, .board-table td { border-right: 1px solid #e9ecef; border-bottom: 1px solid #e9ecef; padding: 3px; vertical-align: top; }
        .board-table thead th { position: sticky; top: 0; background: #f6f8fb; z-index: 2; text-align: center; white-space: nowrap; }
        .board-table .aula { position: sticky; left: 0; background: #f6f8fb; z-index: 1; white-space: nowrap; min-width: 112px; }
        .board-table thead .aula { z-index: 3; }
        .board-table tr.day-start td, .board-table tr.day-start th { border-top: 2px solid #c8d2dc; }
        .board-table td.cell { min-width: 120px; height: 34px; }
        .board-table td.cell.drop-ok { background: #e7f6ec; box-shadow: inset 0 0 0 2px #198754; }
        .board-table td.cell.drop-no { background: #fbeaea; }
        .issue { display: inline-block; min-width: 18px; padding: 0 4px; border-radius: 9px; background: #dc3545; color: #fff; font-weight: 700; text-align: center; margin-left: 4px; }
        .chip { display: block; border-radius: 6px; padding: 2px 5px; margin-bottom: 2px; cursor: grab; border: 1px solid rgba(0,0,0,.08); line-height: 1.2; user-select: none; }
        .chip:focus-visible { outline: 2px solid #0c3d67; outline-offset: 1px; }
        .chip.selected { outline: 2px solid #0c3d67; box-shadow: 0 0 0 3px rgba(12,61,103,.25); }
        .chip strong { font-size: .75rem; }
        .chip small { display: block; color: #495057; font-size: .68rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
        .chip .count { float: right; font-weight: 700; }
        .board-pending { max-height: calc(100vh - 280px); overflow: auto; }
        .board-pending.drop-ok { box-shadow: inset 0 0 0 2px #dc3545; }
        .pending-group + .pending-group { margin-top: .5rem; }
        .board-toast { position: fixed; right: 16px; bottom: 16px; z-index: 1080; max-width: 420px; }
        .board-toast .alert { box-shadow: 0 6px 24px rgba(0,0,0,.15); margin-bottom: .5rem; }
    </style>

    <script>
        (function () {
            const dataUrl = @json(route('allocation.board.data'));
            const moveUrl = @json(route('allocation.board.move'));
            const csrf = @json(csrf_token());
            const state = { data: null, undo: [], redo: [], selected: null, busy: false };
            const $ = id => document.getElementById(id);

            function setStatus(text, cls) { const el = $('b-status'); el.textContent = text; el.className = 'badge ms-auto ' + cls; }
            function toast(messages, kind) {
                if (!messages || !messages.length) return;
                const div = document.createElement('div');
                div.className = 'alert alert-' + kind + ' py-2 small';
                div.innerHTML = messages.map(m => '<div>' + escapeHtml(m) + '</div>').join('');
                $('b-toast').appendChild(div);
                setTimeout(() => div.remove(), kind === 'danger' ? 9000 : 6000);
            }
            function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

            async function load() {
                setStatus('Carregando…', 'bg-secondary');
                const params = new URLSearchParams({ course: $('b-course').value, shift: $('b-shift').value });
                const response = await fetch(dataUrl + '?' + params, { headers: { 'Accept': 'application/json' } });
                state.data = await response.json();
                state.undo = []; state.redo = []; state.selected = null;
                render();
                setStatus('Salvo', 'bg-success');
            }

            async function move(offeringId, from, to, record) {
                if (state.busy) return false;
                state.busy = true;
                setStatus('Salvando…', 'bg-warning text-dark');
                try {
                    const response = await fetch(moveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ offering_id: offeringId, from, to, course: $('b-course').value }),
                    });
                    const result = await response.json();
                    if (result.board) state.data = result.board;
                    if (!response.ok || !result.saved) {
                        toast(result.errors || [result.message || 'Movimento recusado.'], 'danger');
                        setStatus('Recusado', 'bg-danger');
                        render();
                        return false;
                    }
                    if (record) { state.undo.push({ offeringId, from, to }); state.redo = []; }
                    toast(result.warnings, 'warning');
                    setStatus('Salvo', 'bg-success');
                    render();
                    return true;
                } catch (e) {
                    toast(['Falha de comunicação com o servidor.'], 'danger');
                    setStatus('Erro', 'bg-danger');
                    return false;
                } finally {
                    state.busy = false;
                }
            }

            async function undo() {
                const m = state.undo.pop(); if (!m) return;
                if (await move(m.offeringId, m.to, m.from, false)) state.redo.push(m); else state.undo.push(m);
                render();
            }
            async function redo() {
                const m = state.redo.pop(); if (!m) return;
                if (await move(m.offeringId, m.from, m.to, false)) state.undo.push(m); else state.redo.push(m);
                render();
            }

            function chip(o, placement, count) {
                const el = document.createElement('div');
                el.className = 'chip';
                el.tabIndex = 0;
                el.draggable = true;
                el.style.background = o.color;
                el.dataset.offering = o.id;
                el.dataset.class = o.classCode;
                if (placement) { el.dataset.weekday = placement.weekday; el.dataset.letter = placement.letter; }
                const who = o.professor ? o.professor.split(' ')[0] : 'a definir';
                el.title = o.subject + ' — ' + (o.professor || 'professor a definir') + (placement && placement.room ? ' — ' + placement.room : '') + (o.directed_study ? ' (Estudo Dirigido)' : '');
                el.innerHTML = (count ? '<span class="count">' + count + '×</span>' : '') +
                    '<strong>' + escapeHtml(o.abbr) + (o.directed_study ? ' ·ED' : '') + '</strong><small>' + escapeHtml(who) + (placement && placement.room ? ' · ' + escapeHtml(placement.room) : '') + '</small>';
                el.addEventListener('dragstart', ev => {
                    state.selected = { offeringId: o.id, classCode: o.classCode, from: placement ? { weekday: placement.weekday, letter: placement.letter } : null };
                    ev.dataTransfer.setData('text/plain', String(o.id));
                    ev.dataTransfer.effectAllowed = 'move';
                    highlight(o.classCode);
                });
                el.addEventListener('dragend', () => clearHighlight());
                const select = ev => {
                    ev.stopPropagation();
                    const same = state.selected && state.selected.offeringId === o.id && JSON.stringify(state.selected.from) === JSON.stringify(placement ? { weekday: placement.weekday, letter: placement.letter } : null);
                    state.selected = same ? null : { offeringId: o.id, classCode: o.classCode, from: placement ? { weekday: placement.weekday, letter: placement.letter } : null };
                    render();
                    if (state.selected) { highlight(o.classCode); toast(['Aula selecionada: clique num horário da turma ' + o.classCode + ' (ou em "A alocar" para tirar da grade).'], 'info'); }
                };
                el.addEventListener('click', select);
                el.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); select(ev); } });
                if (state.selected && state.selected.offeringId === o.id && JSON.stringify(state.selected.from) === JSON.stringify(placement ? { weekday: placement.weekday, letter: placement.letter } : null)) {
                    el.classList.add('selected');
                }
                return el;
            }

            function highlight(classCode) {
                document.querySelectorAll('td.cell').forEach(td => td.classList.add(td.dataset.class === classCode ? 'drop-ok' : 'drop-no'));
                $('b-pending').closest('.board-pending').classList.add('drop-ok');
            }
            function clearHighlight() {
                document.querySelectorAll('td.cell').forEach(td => td.classList.remove('drop-ok', 'drop-no'));
                $('b-pending').closest('.board-pending').classList.remove('drop-ok');
            }

            async function dropOn(target) {
                const sel = state.selected;
                if (!sel) return;
                clearHighlight();
                state.selected = null;
                if (target === 'pending') {
                    if (sel.from) await move(sel.offeringId, sel.from, null, true); else render();
                    return;
                }
                if (target.classCode !== sel.classCode) { toast(['Essa aula é da turma ' + sel.classCode + '.'], 'danger'); render(); return; }
                const to = { weekday: target.weekday, letter: target.letter };
                if (sel.from && sel.from.weekday === to.weekday && sel.from.letter === to.letter) { render(); return; }
                await move(sel.offeringId, sel.from, to, true);
            }

            function render() {
                const d = state.data; if (!d) return;
                $('b-undo').disabled = !state.undo.length; $('b-redo').disabled = !state.redo.length;
                $('b-score').textContent = d.score.total.toLocaleString('pt-BR');
                $('b-hard').textContent = d.score.hard + ' rígidas';
                $('b-soft').textContent = d.score.soft + ' pts flexíveis';
                $('b-breakdown').innerHTML = d.score.breakdown.length
                    ? d.score.breakdown.map(b => '<span class="me-3 text-nowrap"><span class="badge ' + (b.hard ? 'bg-danger' : 'bg-warning text-dark') + '">' + b.count + '</span> ' + escapeHtml(b.label) + ' <span class="text-muted">(' + b.points.toLocaleString('pt-BR') + ' pts)</span></span>').join('')
                    : '<span class="text-success fw-semibold">✓ Grade sem penalidades</span>';

                const classes = d.classes.map(c => ({ ...c, offerings: c.offerings.map(o => ({ ...o, classCode: c.code })) }));
                const table = document.createElement('table');
                table.className = 'board-table';
                let head = '<thead><tr><th class="aula">Aula</th>';
                classes.forEach(c => { head += '<th scope="col">' + escapeHtml(c.code) + '<br><small class="text-muted">' + c.period + 'º período</small></th>'; });
                table.innerHTML = head + '</tr></thead>';
                const body = document.createElement('tbody');
                Object.entries(d.weekdays).forEach(([weekday, dayLabel]) => {
                    Object.entries(d.letters).forEach(([letter, slot], i) => {
                        const tr = document.createElement('tr');
                        if (i === 0) tr.className = 'day-start';
                        const issues = d.row_issues[weekday + letter] || 0;
                        tr.innerHTML = '<th scope="row" class="aula">' + dayLabel + ' ' + letter + ' <small class="text-muted">' + slot.start + '</small>' + (issues ? '<span class="issue" title="' + issues + ' problema(s) neste horário">' + issues + '</span>' : '') + '</th>';
                        classes.forEach(c => {
                            const td = document.createElement('td');
                            td.className = 'cell';
                            td.dataset.class = c.code; td.dataset.weekday = weekday; td.dataset.letter = letter;
                            c.offerings.forEach(o => o.placements.filter(p => String(p.weekday) === weekday && p.letter === letter).forEach(p => td.appendChild(chip(o, p))));
                            td.addEventListener('dragover', ev => { if (state.selected && state.selected.classCode === c.code) ev.preventDefault(); });
                            td.addEventListener('drop', ev => { ev.preventDefault(); dropOn({ classCode: c.code, weekday: Number(weekday), letter }); });
                            td.addEventListener('click', () => { if (state.selected) dropOn({ classCode: c.code, weekday: Number(weekday), letter }); });
                            tr.appendChild(td);
                        });
                        body.appendChild(tr);
                    });
                });
                table.appendChild(body);
                $('b-grid').replaceChildren(table);

                const pending = $('b-pending');
                pending.replaceChildren();
                let any = false;
                classes.forEach(c => {
                    const items = c.offerings.filter(o => !o.distance && o.expected > o.placements.length);
                    if (!items.length) return;
                    any = true;
                    const group = document.createElement('div');
                    group.className = 'pending-group';
                    group.innerHTML = '<div class="small fw-semibold text-muted">' + escapeHtml(c.code) + '</div>';
                    items.forEach(o => group.appendChild(chip(o, null, o.expected - o.placements.length)));
                    pending.appendChild(group);
                });
                if (!any) pending.innerHTML = '<div class="text-success small">✓ Todas as aulas deste curso/turno estão na grade.</div>';
            }

            const pendingBox = $('b-pending');
            pendingBox.addEventListener('dragover', ev => { if (state.selected && state.selected.from) ev.preventDefault(); });
            pendingBox.addEventListener('drop', ev => { ev.preventDefault(); dropOn('pending'); });
            pendingBox.addEventListener('click', ev => { if (state.selected && state.selected.from && ev.target === pendingBox) dropOn('pending'); });
            $('b-undo').addEventListener('click', undo);
            $('b-redo').addEventListener('click', redo);
            $('b-course').addEventListener('change', load);
            $('b-shift').addEventListener('change', load);
            document.addEventListener('keydown', ev => {
                if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'z') { ev.preventDefault(); undo(); }
                if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'y') { ev.preventDefault(); redo(); }
                if (ev.key === 'Escape' && state.selected) { state.selected = null; clearHighlight(); render(); }
            });
            load();
        })();
    </script>
@endsection
