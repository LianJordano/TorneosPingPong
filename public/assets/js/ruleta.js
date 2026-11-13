/**
 * Party tournament roulette ("torneo fiesta").
 *
 * Plays back the draw script stored on the server, one reveal at a time:
 *   capitan   → first member of each team (doubles)
 *   companero → partner of each team (doubles)
 *   grupo     → team dealt into a group
 *   llave     → team placed into a first-round bracket slot
 *
 * The result is already decided server-side; this file only builds suspense:
 * a slot-machine reel that decelerates, ticks, near-misses and lands, plus
 * sound, confetti, auto/manual pacing, skip, replay and fullscreen.
 * All user data is written with textContent (never innerHTML).
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-ruleta]');
    var dataEl = document.getElementById('fiesta-guion');
    if (!root || !dataEl) { return; }

    var G;
    try { G = JSON.parse(dataEl.textContent); } catch (e) { return; }

    var $ = function (sel) { return root.querySelector(sel); };
    var ui = {
        phase: $('[data-phase]'), count: $('[data-count]'), progress: $('[data-progress]'),
        caption: $('[data-caption]'), reel: $('[data-reel]'), strip: $('[data-strip]'),
        result: $('[data-result]'), next: $('[data-next]'), board: $('[data-board]'),
        intro: $('[data-intro]'), introText: $('[data-intro-text]'), start: $('[data-start]'),
        countdown: $('[data-countdown]'), countdownNum: $('[data-countdown-num]'),
        end: $('[data-end]'), replay: $('[data-replay]'), confetti: $('[data-confetti]'),
        live: $('[data-live]'), auto: $('[data-auto]'), sound: $('[data-sound]'),
        skip: $('[data-skip]'), fullscreen: $('[data-fullscreen]')
    };

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var dobles = G.modalidad === 'DOBLES';
    var pasos = G.pasos || [];
    var ROW = 0; // reel row height in px, read from CSS

    var PHASES = {
        capitan: 'Primeros integrantes',
        companero: 'Compañeros',
        grupo: 'Reparto de grupos',
        llave: 'Cruces de la llave'
    };

    var state = {
        i: 0, running: false, auto: true, sound: true, skipping: false,
        waiting: null, revealed: {}, placed: {}, partner: {}, captain: {}
    };

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------
    function nombre(id) { return (G.participantes && G.participantes[String(id)]) || '?'; }
    function letra(g) { return String.fromCharCode(64 + Number(g)); }
    function equipoLabel(numero) {
        var eq = G.equipos[String(numero)];
        if (!eq) { return '?'; }
        return eq.p2 ? nombre(eq.p1) + ' & ' + nombre(eq.p2) : nombre(eq.p1);
    }
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text !== undefined) { n.textContent = text; }
        return n;
    }
    function wait(ms) {
        return new Promise(function (r) { setTimeout(r, state.skipping ? 0 : ms); });
    }
    function shuffle(xs) {
        var a = xs.slice();
        for (var k = a.length - 1; k > 0; k--) {
            var j = Math.floor(Math.random() * (k + 1));
            var t = a[k]; a[k] = a[j]; a[j] = t;
        }
        return a;
    }
    function announce(text) { ui.live.textContent = text; }

    // ------------------------------------------------------------------
    //  Sound (WebAudio, generated — no files)
    // ------------------------------------------------------------------
    var audio = null;
    function ctx() {
        if (!state.sound) { return null; }
        if (!audio) {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) { return null; }
            try { audio = new AC(); } catch (e) { return null; }
        }
        if (audio.state === 'suspended') { audio.resume(); }
        return audio;
    }
    function tone(freq, dur, type, gain, delay) {
        var a = ctx();
        if (!a) { return; }
        var t0 = a.currentTime + (delay || 0);
        var o = a.createOscillator();
        var g = a.createGain();
        o.type = type || 'sine';
        o.frequency.setValueAtTime(freq, t0);
        g.gain.setValueAtTime(0.0001, t0);
        g.gain.exponentialRampToValueAtTime(gain || 0.08, t0 + 0.01);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        o.connect(g).connect(a.destination);
        o.start(t0);
        o.stop(t0 + dur + 0.02);
    }
    var sfx = {
        tick: function () { tone(1400 + Math.random() * 300, 0.035, 'square', 0.025); },
        reveal: function () { tone(660, 0.18, 'triangle', 0.09); tone(990, 0.28, 'triangle', 0.08, 0.09); },
        pair: function () { [523, 659, 784].forEach(function (f, k) { tone(f, 0.3, 'triangle', 0.07, k * 0.07); }); },
        drum: function () { for (var k = 0; k < 10; k++) { tone(110 + k * 6, 0.08, 'sawtooth', 0.02 + k * 0.004, k * 0.09); } },
        fanfare: function () {
            [523, 659, 784, 1046, 784, 1046].forEach(function (f, k) { tone(f, 0.35, 'triangle', 0.08, k * 0.13); });
        }
    };

    // ------------------------------------------------------------------
    //  Confetti
    // ------------------------------------------------------------------
    function confetti(amount) {
        if (reduceMotion || state.skipping) { return; }
        var n = amount || 36;
        for (var k = 0; k < n; k++) {
            var p = el('span', 'rt-confetti__piece rt-confetti__piece--' + (k % 4));
            p.style.setProperty('--x', (Math.random() * 100).toFixed(1) + '%');
            p.style.setProperty('--dx', ((Math.random() - 0.5) * 240).toFixed(0) + 'px');
            p.style.setProperty('--r', (Math.random() * 720 - 360).toFixed(0) + 'deg');
            p.style.setProperty('--d', (900 + Math.random() * 900).toFixed(0) + 'ms');
            p.style.setProperty('--delay', (Math.random() * 200).toFixed(0) + 'ms');
            ui.confetti.appendChild(p);
        }
        setTimeout(function () { ui.confetti.textContent = ''; }, 2200);
    }

    // ------------------------------------------------------------------
    //  Board
    // ------------------------------------------------------------------
    var board = { pairs: null, groups: null, bracket: null };

    function buildBoard() {
        ui.board.textContent = '';
        board = { pairs: null, groups: null, bracket: null };
        var numEquipos = Object.keys(G.equipos).length;

        if (dobles) {
            var wrap = el('section', 'rt-panel');
            wrap.appendChild(el('h2', 'rt-panel__title', 'Parejas'));
            var grid = el('div', 'rt-pairs');
            board.pairs = {};
            for (var k = 1; k <= numEquipos; k++) {
                var card = el('div', 'rt-pair');
                card.appendChild(el('span', 'rt-pair__num', String(k)));
                var s1 = el('span', 'rt-slot', '?');
                var s2 = el('span', 'rt-slot', '?');
                card.appendChild(s1);
                card.appendChild(el('span', 'rt-pair__amp', '&'));
                card.appendChild(s2);
                grid.appendChild(card);
                board.pairs[k] = { card: card, s1: s1, s2: s2 };
            }
            wrap.appendChild(grid);
            ui.board.appendChild(wrap);
        }

        if (G.formato === 'GRUPOS') {
            var wrapG = el('section', 'rt-panel');
            wrapG.appendChild(el('h2', 'rt-panel__title', 'Grupos'));
            var cols = el('div', 'rt-groups');
            board.groups = {};
            for (var g = 1; g <= G.numGrupos; g++) {
                var col = el('div', 'rt-group');
                col.appendChild(el('span', 'rt-group__letter', letra(g)));
                var list = el('ol', 'rt-group__list');
                col.appendChild(list);
                cols.appendChild(col);
                board.groups[g] = { col: col, list: list };
            }
            wrapG.appendChild(cols);
            ui.board.appendChild(wrapG);
        } else {
            var wrapB = el('section', 'rt-panel');
            wrapB.appendChild(el('h2', 'rt-panel__title', G.rondas > 1 ? 'Primera ronda' : 'Final'));
            var list2 = el('div', 'rt-matches');
            board.bracket = {};
            (G.cruces || []).slice().sort(function (x, y) { return x.orden - y.orden; }).forEach(function (c) {
                var m = el('div', 'rt-match' + (c.bye ? ' is-bye' : ''));
                var a = el('span', 'rt-slot', c.a === null ? 'Pasa directo' : '?');
                var b = el('span', 'rt-slot', c.b === null ? 'Pasa directo' : '?');
                if (c.a === null) { a.classList.add('is-bye'); }
                if (c.b === null) { b.classList.add('is-bye'); }
                m.appendChild(a);
                m.appendChild(el('span', 'rt-match__vs', 'vs'));
                m.appendChild(b);
                list2.appendChild(m);
                board.bracket[c.orden] = { card: m, a: a, b: b };
            });
            wrapB.appendChild(list2);
            ui.board.appendChild(wrapB);
        }
    }

    function fill(slot, text) {
        slot.textContent = text;
        slot.classList.add('is-filled');
        if (!state.skipping && !reduceMotion) {
            slot.classList.remove('is-pop');
            void slot.offsetWidth; // restart the animation
            slot.classList.add('is-pop');
        }
        var host = slot.closest('.rt-pair, .rt-match, .rt-group');
        if (host && !state.skipping) {
            host.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
        }
    }

    // ------------------------------------------------------------------
    //  Reel
    // ------------------------------------------------------------------
    function pool(paso) {
        if (paso.tipo === 'capitan' || paso.tipo === 'companero') {
            return Object.keys(G.participantes).filter(function (id) { return !state.revealed[id]; }).map(nombre);
        }
        return Object.keys(G.equipos).filter(function (n) { return !state.placed[n]; }).map(equipoLabel);
    }

    function winnerOf(paso) {
        return paso.tipo === 'capitan' || paso.tipo === 'companero' ? nombre(paso.participante) : equipoLabel(paso.equipo);
    }

    function spin(candidates, winner, last) {
        ui.reel.classList.remove('is-landed');
        ui.strip.textContent = '';
        ui.strip.getAnimations && ui.strip.getAnimations().forEach(function (a) { a.cancel(); });

        if (state.skipping || reduceMotion || candidates.length <= 1) {
            ui.strip.appendChild(el('div', 'rt-reel__item is-winner', winner));
            ui.strip.style.transform = 'translateY(' + ROW + 'px)';
            ui.reel.classList.add('is-landed');
            return Promise.resolve();
        }

        var others = candidates.filter(function (c) { return c !== winner; });
        if (others.length === 0) { others = [winner]; }
        var length = last ? 46 : 30 + Math.min(10, candidates.length);
        var items = [];
        var bag = [];
        for (var k = 0; k < length - 2; k++) {
            if (bag.length === 0) { bag = shuffle(others); }
            items.push(bag.pop());
        }
        // Near miss: the item right before the winner is a real alternative.
        items.push(winner);
        items.push(others[Math.floor(Math.random() * others.length)]);
        items.unshift(others[Math.floor(Math.random() * others.length)]);

        items.forEach(function (text, idx) {
            ui.strip.appendChild(el('div', 'rt-reel__item' + (idx === items.length - 2 ? ' is-target' : ''), text));
        });

        var targetIndex = items.length - 2;
        var from = ROW;                         // item 0 centred under item -1 ... visual offset
        var to = ROW - targetIndex * ROW;
        var duration = last ? 5200 : 2600 + Math.random() * 900;

        return new Promise(function (resolve) {
            var anim = ui.strip.animate(
                [{ transform: 'translateY(' + from + 'px)' }, { transform: 'translateY(' + to + 'px)' }],
                { duration: duration, easing: 'cubic-bezier(0.05, 0.7, 0.1, 1)', fill: 'forwards' }
            );
            var lastIdx = -1;
            var raf = function () {
                if (anim.playState !== 'running') { return; }
                var m = new DOMMatrixReadOnly(getComputedStyle(ui.strip).transform);
                var idx = Math.round((ROW - m.m42) / ROW);
                if (idx !== lastIdx) { lastIdx = idx; sfx.tick(); }
                requestAnimationFrame(raf);
            };
            requestAnimationFrame(raf);
            anim.onfinish = function () {
                ui.strip.style.transform = 'translateY(' + to + 'px)';
                ui.reel.classList.add('is-landed');
                resolve();
            };
            state.cancelSpin = function () { anim.finish(); };
        });
    }

    // ------------------------------------------------------------------
    //  Steps
    // ------------------------------------------------------------------
    function captionFor(paso, isLast) {
        if (isLast && !state.skipping) { return 'Y el último lugar es para…'; }
        switch (paso.tipo) {
            case 'capitan': return 'Pareja ' + paso.equipo + ': ¿quién la arranca?';
            case 'companero': return '¿Quién acompaña a ' + nombre(state.captain[paso.equipo]) + '?';
            default: return dobles ? '¿Qué pareja sale ahora?' : '¿Quién sale ahora?';
        }
    }

    function applyReveal(paso) {
        switch (paso.tipo) {
            case 'capitan':
                state.revealed[paso.participante] = true;
                state.captain[paso.equipo] = paso.participante;
                fill(board.pairs[paso.equipo].s1, nombre(paso.participante));
                board.pairs[paso.equipo].card.classList.add('is-started');
                return nombre(paso.participante) + ' abre la pareja ' + paso.equipo;
            case 'companero':
                state.revealed[paso.participante] = true;
                fill(board.pairs[paso.equipo].s2, nombre(paso.participante));
                board.pairs[paso.equipo].card.classList.add('is-complete');
                return nombre(state.captain[paso.equipo]) + ' & ' + nombre(paso.participante);
            case 'grupo':
                state.placed[paso.equipo] = true;
                var li = el('li', 'rt-group__item');
                board.groups[paso.grupo].list.appendChild(li);
                fill(li, equipoLabel(paso.equipo));
                return equipoLabel(paso.equipo) + ' → Grupo ' + letra(paso.grupo);
            case 'llave':
                state.placed[paso.equipo] = true;
                var cruce = board.bracket[paso.partido];
                fill(cruce[paso.lado], equipoLabel(paso.equipo));
                cruce.card.classList.add('is-started');
                if (cruce.a.classList.contains('is-filled') && cruce.b.classList.contains('is-filled')) {
                    cruce.card.classList.add('is-complete');
                }
                if (cruce.a.classList.contains('is-bye') || cruce.b.classList.contains('is-bye')) {
                    cruce.card.classList.add('is-complete');
                }
                return equipoLabel(paso.equipo) + ' → Cruce ' + (paso.partido + 1);
        }
        return '';
    }

    function updateHud() {
        var total = pasos.length;
        var paso = pasos[Math.min(state.i, total - 1)];
        ui.count.textContent = Math.min(state.i, total) + ' / ' + total;
        ui.progress.style.setProperty('--p', total ? (state.i / total) : 0);
        if (paso) { ui.phase.textContent = PHASES[paso.tipo] || 'Sorteo'; }
    }

    async function playStep() {
        var paso = pasos[state.i];
        var isLast = state.i === pasos.length - 1;
        var phaseChanged = state.i === 0 || pasos[state.i - 1].tipo !== paso.tipo;
        updateHud();

        if (phaseChanged && !state.skipping) {
            root.classList.add('is-phase-change');
            ui.caption.textContent = PHASES[paso.tipo];
            ui.result.textContent = '';
            await wait(1300);
            root.classList.remove('is-phase-change');
        }

        ui.caption.textContent = captionFor(paso, isLast);
        ui.result.textContent = '';
        root.classList.add('is-spinning');
        if (isLast && !state.skipping) { sfx.drum(); await wait(900); }

        await spin(pool(paso), winnerOf(paso), isLast);

        root.classList.remove('is-spinning');
        var text = applyReveal(paso);
        ui.result.textContent = text;
        announce(text);
        state.i++;
        updateHud();

        if (!state.skipping) {
            if (paso.tipo === 'companero') { sfx.pair(); confetti(28); } else { sfx.reveal(); }
            if (isLast) { confetti(90); }
        }
    }

    function waitForNext() {
        return new Promise(function (resolve) {
            ui.next.hidden = false;
            ui.next.focus({ preventScroll: true });
            state.waiting = function () {
                state.waiting = null;
                ui.next.hidden = true;
                resolve();
            };
        });
    }

    async function run() {
        state.running = true;
        while (state.i < pasos.length) {
            await playStep();
            if (state.i >= pasos.length) { break; }
            if (state.skipping) { continue; }
            if (state.auto) { await wait(1500); }
            if (!state.auto && !state.skipping) { await waitForNext(); }
        }
        state.running = false;
        finish();
    }

    function finish() {
        ui.next.hidden = true;
        ui.caption.textContent = '¡Listo!';
        updateHud();
        if (!state.skipping) { sfx.fanfare(); confetti(120); }
        setTimeout(function () { ui.end.hidden = false; ui.end.querySelector('a').focus(); }, state.skipping ? 0 : 1400);
    }

    async function countdown() {
        if (reduceMotion) { return; }
        ui.countdown.hidden = false;
        for (var n = 3; n >= 1; n--) {
            ui.countdownNum.textContent = String(n);
            ui.countdownNum.classList.remove('is-pop');
            void ui.countdownNum.offsetWidth;
            ui.countdownNum.classList.add('is-pop');
            tone(n === 1 ? 880 : 440, 0.18, 'triangle', 0.08);
            await wait(800);
        }
        ui.countdown.hidden = true;
    }

    function reset() {
        state.i = 0; state.revealed = {}; state.placed = {}; state.captain = {}; state.skipping = false;
        ui.end.hidden = true;
        ui.result.textContent = '';
        ui.caption.textContent = '¿Listos?';
        ui.strip.textContent = '';
        buildBoard();
        updateHud();
    }

    async function start() {
        ctx(); // unlock audio within the user gesture
        ui.intro.hidden = true;
        await countdown();
        if (!state.running && state.i < pasos.length) { run(); }
    }

    // ------------------------------------------------------------------
    //  Controls
    // ------------------------------------------------------------------
    function setPressed(btn, on) { btn.setAttribute('aria-pressed', on ? 'true' : 'false'); }

    ui.start.addEventListener('click', start);
    ui.next.addEventListener('click', function () { if (state.waiting) { state.waiting(); } });
    ui.auto.addEventListener('click', function () {
        state.auto = !state.auto;
        setPressed(ui.auto, state.auto);
        if (state.auto && state.waiting) { state.waiting(); }
    });
    ui.sound.addEventListener('click', function () {
        state.sound = !state.sound;
        setPressed(ui.sound, state.sound);
    });
    ui.skip.addEventListener('click', function () {
        if (state.i >= pasos.length) { return; }
        if (!ui.intro.hidden) { ui.intro.hidden = true; }
        ui.countdown.hidden = true;
        state.skipping = true;
        if (state.cancelSpin) { state.cancelSpin(); }
        if (state.waiting) { state.waiting(); }
        if (!state.running) { run(); }
    });
    ui.replay.addEventListener('click', function () {
        reset();
        ui.intro.hidden = false;
        ui.start.focus();
    });
    ui.fullscreen.addEventListener('click', toggleFullscreen);

    function toggleFullscreen() {
        if (document.fullscreenElement) { document.exitFullscreen(); }
        else if (root.requestFullscreen) { root.requestFullscreen().catch(function () {}); }
    }

    document.addEventListener('keydown', function (ev) {
        if (ev.target.closest && ev.target.closest('input, textarea, select')) { return; }
        if (ev.key === ' ' || ev.key === 'Enter' || ev.key === 'ArrowRight') {
            if (!ui.intro.hidden) { ev.preventDefault(); start(); return; }
            if (state.waiting) { ev.preventDefault(); state.waiting(); }
        } else if (ev.key === 'f' || ev.key === 'F') {
            toggleFullscreen();
        } else if (ev.key === 'p' || ev.key === 'P') {
            ui.auto.click();
        } else if (ev.key === 'm' || ev.key === 'M') {
            ui.sound.click();
        }
    });

    // ------------------------------------------------------------------
    //  Init
    // ------------------------------------------------------------------
    ROW = parseFloat(getComputedStyle(ui.reel).getPropertyValue('--row')) || 72;
    var numEquipos = Object.keys(G.equipos).length;
    var numPart = Object.keys(G.participantes).length;
    ui.introText.textContent = dobles
        ? numPart + ' participantes, ' + numEquipos + ' parejas. Primero salen los primeros integrantes y después sus compañeros.'
        : numPart + ' jugadores. La ruleta los va sacando de a uno.';
    reset();
    ui.start.focus({ preventScroll: true });
})();
