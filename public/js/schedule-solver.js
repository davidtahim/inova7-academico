/*
 * Resolvedor automático de horários (RF05/RN01/RN02) — Inova7 Acadêmico.
 *
 * Roda como Web Worker no navegador (não trava a tela) e também em Node (testes).
 * Entrada: problema de App\Services\SolverProblemBuilder. Saída: professor, sala e
 * aulas (dia → letras) por oferta. O servidor revalida tudo antes de gravar.
 *
 * Estratégia: construção gulosa (casos mais difíceis primeiro, blocos de 2 aulas),
 * depois simulated annealing com movimentos de aula, de dia, de professor e de sala.
 */
(function (root) {
    'use strict';

    const DAYS = [1, 2, 3, 4, 5, 6];

    function Solver(problem, options) {
        options = options || {};
        this.p = problem;
        this.w = problem.weights;
        this.keepExisting = !!options.keepExisting;
        this.rand = options.seed ? mulberry32(options.seed) : Math.random;
        this.prof = {};
        problem.professors.forEach(pr => { this.prof[pr.id] = pr; });
        this.letters = {};
        Object.entries(problem.shifts).forEach(([shift, map]) => { this.letters[shift] = Object.keys(map); });
        this.build();
    }

    /* ---------- estado inicial ---------- */
    Solver.prototype.build = function () {
        const p = this.p;
        this.items = p.offerings.map(o => {
            const units = [];
            const locked = [];
            if (this.keepExisting || o.frozen) {
                Object.entries(o.placements || {}).forEach(([d, letters]) => letters.forEach(l => {
                    if (units.length < o.expected) { units.push({ d: Number(d), l }); locked.push(true); }
                }));
            }
            if (o.frozen) {
                // Bloqueio fixo: ocupa professor, sala e turma, mas nunca é alterado nem devolvido.
                return { o, units, locked, professor: o.professor, profLocked: true, room: o.room, roomLocked: true, frozen: true };
            }
            let professor = o.fixedProfessor || (this.keepExisting ? o.professor : null) || null;
            return {
                o, units, locked,
                professor,
                profLocked: !!o.fixedProfessor || (this.keepExisting && !!o.professor),
                room: this.keepExisting ? (o.room || null) : null,
                roomLocked: this.keepExisting && !!o.room && o.rooms.includes(o.room),
            };
        });

        this.movable = this.items.filter(it => !it.frozen);
        // Mais difíceis primeiro: menos candidatos, mais aulas.
        const order = this.movable.slice().sort((a, b) =>
            (a.o.candidates.length || 99) - (b.o.candidates.length || 99) || b.o.expected - a.o.expected);
        this.occ = { cls: new Map(), prof: new Map(), room: new Map() };
        this.items.forEach(it => it.units.forEach(u => this.occupy(it, u, +1)));

        order.forEach(it => {
            if (!it.professor && it.o.candidates.length) it.professor = this.pickProfessor(it);
            if (!it.room || !it.o.rooms.includes(it.room)) {
                if (!it.roomLocked) it.room = it.o.rooms[0] || null;
            }
            while (it.units.length < it.o.expected) {
                const need = Math.min(2, it.o.expected - it.units.length);
                const block = this.bestBlock(it, need);
                block.forEach(u => { it.units.push(u); it.locked.push(false); this.occupy(it, u, +1); });
            }
            if (!it.roomLocked && it.o.rooms.length) it.room = this.bestRoom(it);
        });
    };

    Solver.prototype.key = function (shift, d, l) { return shift + '|' + d + '|' + l; };

    Solver.prototype.occupy = function (it, u, delta) {
        const k = this.key(it.o.shift, u.d, u.l);
        const bump = (map, key) => map.set(key, (map.get(key) || 0) + delta);
        bump(this.occ.cls, it.o.class + '#' + k);
        if (it.professor) bump(this.occ.prof, it.professor + '#' + k);
        if (it.room) bump(this.occ.room, it.room + '#' + k);
    };

    Solver.prototype.pref = function (professorId, d, shift, l) {
        const pr = this.prof[professorId];
        if (!pr || !pr.hasGrid) return 'unknown';
        return pr.availability[d + '|' + this.p.shifts[shift][l]] || 'unavailable';
    };

    Solver.prototype.pickProfessor = function (it) {
        let best = null, bestScore = Infinity;
        it.o.candidates.forEach(id => {
            const pr = this.prof[id];
            let s = 0;
            if (pr && pr.hasGrid) {
                const prefs = Object.values(pr.availability);
                s -= prefs.filter(v => v === 'preferred').length * 2 + prefs.filter(v => v === 'available').length;
            }
            // carga já atribuída
            this.items.forEach(other => { if (other.professor === id) s += other.o.expected * 3; });
            s += this.rand() * 0.5;
            if (s < bestScore) { bestScore = s; best = id; }
        });
        return best;
    };

    Solver.prototype.unitCost = function (it, d, l) {
        const k = this.key(it.o.shift, d, l);
        let c = 0;
        if ((this.occ.cls.get(it.o.class + '#' + k) || 0) > 0) c += it.o.directed ? 2 : 1000;
        if (it.professor && (this.occ.prof.get(it.professor + '#' + k) || 0) > 0) c += it.o.directed ? 2 : 1000;
        if (it.professor) {
            const pref = this.pref(it.professor, d, it.o.shift, l);
            if (pref === 'unavailable') c += 1000; else if (pref === 'available') c += 1;
        }
        if (it.room && (this.occ.room.get(it.room + '#' + k) || 0) > 0) c += it.o.directed ? 2 : 50;
        return c;
    };

    Solver.prototype.bestBlock = function (it, size) {
        const letters = this.letters[it.o.shift];
        let best = null, bestCost = Infinity;
        DAYS.forEach(d => {
            const usedToday = it.units.filter(u => u.d === d).length;
            for (let i = 0; i + size <= letters.length; i++) {
                const block = letters.slice(i, i + size).map(l => ({ d, l }));
                if (block.some(u => it.units.some(x => x.d === u.d && x.l === u.l))) continue;
                let cost = block.reduce((s, u) => s + this.unitCost(it, u.d, u.l), 0) + usedToday * 4 + this.rand() * 0.3;
                if (cost < bestCost) { bestCost = cost; best = block; }
            }
        });
        return best || [{ d: 1, l: letters[0] }];
    };

    Solver.prototype.bestRoom = function (it) {
        let best = it.room, bestCost = Infinity;
        it.o.rooms.forEach(code => {
            let c = 0;
            it.units.forEach(u => { c += (this.occ.room.get(code + '#' + this.key(it.o.shift, u.d, u.l)) || 0); });
            if (it.room === code) c -= 0.5; // estabilidade
            if (c < bestCost) { bestCost = c; best = code; }
        });
        if (best !== it.room) {
            it.units.forEach(u => this.occupy(it, u, -1));
            it.room = best;
            it.units.forEach(u => this.occupy(it, u, +1));
        }
        return best;
    };

    /* ---------- pontuação (espelha ScheduleScorer + validador) ---------- */
    Solver.prototype.score = function () {
        const w = this.w;
        const counts = { conflict: 0, unavailable: 0, gap: 0, over_day: 0, over_days: 0, concentrated: 0, not_preferred: 0, consecutive: 0, no_professor: 0 };
        const byClass = new Map(), byProf = new Map(), byRoom = new Map();
        const profDays = new Map();
        const push = (map, key, it) => { const a = map.get(key); a ? a.push(it) : map.set(key, [it]); };

        this.items.forEach(it => {
            const o = it.o;
            if (!it.professor && !it.frozen) counts.no_professor++;
            const perDay = {};
            it.units.forEach(u => {
                const k = this.key(o.shift, u.d, u.l);
                push(byClass, o.class + '#' + k, it);
                if (it.professor) {
                    push(byProf, it.professor + '#' + k, it);
                    const pref = this.pref(it.professor, u.d, o.shift, u.l);
                    if (pref === 'unavailable') counts.unavailable++;
                    else if (pref === 'available') counts.not_preferred++;
                    const pd = profDays.get(it.professor) || {};
                    (pd[u.d] = pd[u.d] || {})[o.shift] = (pd[u.d][o.shift] || new Set()).add(u.l);
                    profDays.set(it.professor, pd);
                }
                if (it.room) push(byRoom, it.room + '#' + k, it);
                (perDay[u.d] = perDay[u.d] || []).push(u.l);
            });
            const order = this.letters[o.shift];
            Object.values(perDay).forEach(ls => {
                if (ls.length >= 3) counts.concentrated++;
                const idx = ls.map(l => order.indexOf(l)).sort((a, b) => a - b);
                let run = 1;
                for (let i = 1; i < idx.length; i++) {
                    if (idx[i] === idx[i - 1]) { counts.conflict++; continue; } // aula duplicada
                    run = idx[i] === idx[i - 1] + 1 ? run + 1 : 1;
                    if (run > o.maxConsecutive) counts.consecutive++;
                }
            });
        });

        const pairs = (map, ok) => map.forEach(list => {
            for (let i = 0; i < list.length; i++) for (let j = i + 1; j < list.length; j++) {
                const a = list[i], b = list[j];
                if (a === b) continue;
                if (a.o.directed && b.o.directed) continue; // grupos de Estudo Dirigido simultâneos
                if (ok && ok(a, b)) continue;
                counts.conflict++;
            }
        });
        const joint = (a, b) => a.o.subjectKey === b.o.subjectKey && a.o.class !== b.o.class && a.professor && a.professor === b.professor;
        pairs(byClass, null);
        pairs(byProf, joint);
        pairs(byRoom, joint);

        profDays.forEach((days, id) => {
            const pr = this.prof[id] || {};
            Object.values(days).forEach(byShift => {
                let total = 0;
                Object.entries(byShift).forEach(([shift, set]) => {
                    const idx = Array.from(set).map(l => this.letters[shift].indexOf(l));
                    total += idx.length;
                    counts.gap += (Math.max(...idx) - Math.min(...idx) + 1) - idx.length;
                });
                if (pr.maxPerDay && total > pr.maxPerDay) counts.over_day += total - pr.maxPerDay;
            });
            const n = Object.keys(days).length;
            if (pr.maxDays && n > pr.maxDays) counts.over_days += n - pr.maxDays;
        });

        let total = 0, hard = 0;
        Object.entries(counts).forEach(([type, n]) => {
            const weight = w[type] || 0;
            total += n * weight;
            if (weight >= 1000) hard += n;
        });
        return { total, hard, counts };
    };

    /* ---------- vizinhança ---------- */
    Solver.prototype.randomMove = function () {
        const items = this.movable;
        if (!items.length) return null;
        const it = items[Math.floor(this.rand() * items.length)];
        const r = this.rand();
        const free = it.units.map((u, i) => i).filter(i => !it.locked[i]);
        const letters = this.letters[it.o.shift];

        if (r < 0.55 && free.length) { // mover uma aula
            const i = free[Math.floor(this.rand() * free.length)];
            const old = it.units[i];
            const nu = { d: DAYS[Math.floor(this.rand() * 6)], l: letters[Math.floor(this.rand() * letters.length)] };
            if (it.units.some(u => u.d === nu.d && u.l === nu.l)) return null;
            it.units[i] = nu;
            return () => { it.units[i] = old; };
        }
        if (r < 0.8 && free.length) { // mover o dia inteiro (mantém o bloco)
            const days = [...new Set(free.map(i => it.units[i].d))];
            const from = days[Math.floor(this.rand() * days.length)];
            const to = DAYS[Math.floor(this.rand() * 6)];
            if (from === to) return null;
            const idx = free.filter(i => it.units[i].d === from);
            if (idx.some(i => it.units.some(u => u.d === to && u.l === it.units[i].l))) return null;
            const old = idx.map(i => it.units[i]);
            idx.forEach(i => { it.units[i] = { d: to, l: it.units[i].l }; });
            return () => { idx.forEach((i, k) => { it.units[i] = old[k]; }); };
        }
        if (r < 0.93 && !it.profLocked && it.o.candidates.length > 1) { // trocar professor
            const old = it.professor;
            const next = it.o.candidates[Math.floor(this.rand() * it.o.candidates.length)];
            if (next === old) return null;
            it.professor = next;
            return () => { it.professor = old; };
        }
        if (!it.roomLocked && it.o.rooms.length > 1) { // trocar sala
            const old = it.room;
            const next = it.o.rooms[Math.floor(this.rand() * it.o.rooms.length)];
            if (next === old) return null;
            it.room = next;
            return () => { it.room = old; };
        }
        return null;
    };

    Solver.prototype.snapshot = function () {
        return this.items.map(it => ({ professor: it.professor, room: it.room, units: it.units.map(u => ({ d: u.d, l: u.l })) }));
    };
    Solver.prototype.restore = function (snap) {
        snap.forEach((s, i) => { const it = this.items[i]; it.professor = s.professor; it.room = s.room; it.units = s.units.map(u => ({ d: u.d, l: u.l })); });
    };

    Solver.prototype.solution = function (snap) {
        return this.items.map((it, i) => {
            if (it.frozen) return null;
            const s = snap ? snap[i] : { professor: it.professor, room: it.room, units: it.units };
            const placements = {};
            s.units.forEach(u => { (placements[u.d] = placements[u.d] || []).push(u.l); });
            Object.keys(placements).forEach(d => placements[d].sort());
            return { id: it.o.id, professor_id: s.professor, room: s.room, placements };
        }).filter(Boolean);
    };

    /**
     * Executa o annealing por até timeLimitMs, chamando onProgress periodicamente.
     * shouldStop() permite parada manual (RF05/RNF02).
     */
    Solver.prototype.run = async function (timeLimitMs, onProgress, shouldStop, yieldFn) {
        const start = Date.now();
        let current = this.score();
        let best = current, bestSnap = this.snapshot();
        let iterations = 0, lastReport = 0;
        const T0 = 1500, T1 = 0.3;

        while (true) {
            const elapsed = Date.now() - start;
            if (elapsed >= timeLimitMs || best.total === 0 || (shouldStop && shouldStop())) break;
            const T = T0 * Math.pow(T1 / T0, elapsed / timeLimitMs);
            for (let k = 0; k < 400; k++) {
                const undo = this.randomMove();
                if (!undo) continue;
                iterations++;
                const next = this.score();
                const delta = next.total - current.total;
                if (delta <= 0 || this.rand() < Math.exp(-delta / T)) {
                    current = next;
                    if (current.total < best.total) { best = current; bestSnap = this.snapshot(); }
                } else {
                    undo();
                }
            }
            if (onProgress && Date.now() - lastReport > 250) {
                lastReport = Date.now();
                onProgress({ elapsed: Date.now() - start, iterations, current: current.total, best: best.total, hard: best.hard, counts: best.counts });
            }
            if (yieldFn) await yieldFn();
        }
        this.restore(bestSnap);
        return { elapsed: Date.now() - start, iterations, best: best.total, hard: best.hard, counts: best.counts, solution: this.solution(bestSnap) };
    };

    function mulberry32(a) {
        return function () {
            a |= 0; a = a + 0x6D2B79F5 | 0;
            let t = Math.imul(a ^ a >>> 15, 1 | a);
            t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
            return ((t ^ t >>> 14) >>> 0) / 4294967296;
        };
    }

    /* ---------- Web Worker ---------- */
    if (typeof self !== 'undefined' && typeof self.postMessage === 'function' && typeof window === 'undefined') {
        let stop = false;
        self.onmessage = async (ev) => {
            const msg = ev.data || {};
            if (msg.type === 'stop') { stop = true; return; }
            if (msg.type !== 'start') return;
            stop = false;
            const solver = new Solver(msg.problem, { keepExisting: msg.keepExisting });
            const initial = solver.score();
            self.postMessage({ type: 'initial', total: initial.total, hard: initial.hard, counts: initial.counts });
            const result = await solver.run(
                msg.timeLimitMs,
                progress => self.postMessage({ type: 'progress', ...progress }),
                () => stop,
                () => new Promise(r => setTimeout(r, 0))
            );
            self.postMessage({ type: 'done', ...result });
        };
    }

    root.InovaScheduleSolver = Solver;
    if (typeof module !== 'undefined' && module.exports) module.exports = { Solver };
})(typeof self !== 'undefined' ? self : this);
