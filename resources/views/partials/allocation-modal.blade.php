{{--
    Modal de edição de uma disciplina da turma (professor, horário por dia, sala e carga).
    Variáveis: $allocModal (AllocationModal::forOfferings) e $allocContext (AllocationModal::context).
    Qualquer elemento com data-edit="{id da oferta}" abre o modal.
--}}
@php
    $professors = $allocContext['professors'];
    $weekdays = $allocContext['weekdays'];
    $directedStudyMax = $allocContext['directedStudyMax'];
@endphp
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
        $jsShifts = $allocContext['shifts'];
        $jsBusy = $allocContext['busy'];
        $jsAvailability = $allocContext['availability'];
    @endphp
    <script>
        (function () {
            const data = @json($allocModal);
            const professors = @json($jsProfessors);
            const busy = @json($jsBusy);
            const availability = @json($jsAvailability);
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

            document.addEventListener('click', ev => {
                const trigger = ev.target.closest('[data-edit]');
                if (trigger && data[trigger.dataset.edit]) { ev.preventDefault(); open(trigger.dataset.edit); }
            });
            // Erro ao salvar: reabre o modal da disciplina com as mensagens e o que foi preenchido.
            const failed = Object.values(data).find(o => o.open);
            if (failed) window.addEventListener('load', () => open(failed.id));
        })();
    </script>
