/**
 * Party tournament roulette ("torneo fiesta").
 *
 * Plays back the draw script stored on the server, one reveal at a time:
 *   capitan   → first member of each team (doubles)
 *   companero → partner of each team (doubles)
 *   grupo     → team dealt into a group
 *   llave     → team placed into a first-round bracket slot
 *
 * The result is already decided server-side; this file builds live-draw
 * tension: custom deceleration curve with near-miss crawl, synced mechanical
 * ticks + rising tension drone/heartbeat + dramatic silence before impact,
 * spotlight vignette, doubles matchup suspense banner, live pool counter,
 * safe skip controls, session persistence and full keyboard/a11y support.
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
        bar: $('[data-bar]'), main: $('[data-main]'),
        phase: $('[data-phase]'), count: $('[data-count]'), progress: $('[data-progress]'),
        poolPill: $('[data-pool-pill]'), poolCount: $('[data-pool-count]'),
        caption: $('[data-caption]'),
        matchup: $('[data-matchup]'), matchupCap: $('[data-matchup-cap]'),
        matchupSep: $('[data-matchup-sep]'), matchupPartner: $('[data-matchup-partner]'),
        reel: $('[data-reel]'), strip: $('[data-strip]'),
        result: $('[data-result]'), next: $('[data-next]'), board: $('[data-board]'),
        intro: $('[data-intro]'), introText: $('[data-intro-text]'),
        start: $('[data-start]'), startLabel: $('[data-start-label]'),
        resume: $('[data-resume]'), resumeLabel: $('[data-resume-label]'),
        countdown: $('[data-countdown]'), countdownNum: $('[data-countdown-num]'),
        end: $('[data-end]'), replay: $('[data-replay]'), confetti: $('[data-confetti]'),
        live: $('[data-live]'), auto: $('[data-auto]'), sound: $('[data-sound]'),
        skipStep: $('[data-skip-step]'), skip: $('[data-skip]'), skipLabel: $('[data-skip-label]'),
        fullscreen: $('[data-fullscreen]')
    };

    var motionMq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var reduceMotion = motionMq ? motionMq.matches : false;
    if (motionMq && motionMq.addEventListener) {
        motionMq.addEventListener('change', function (ev) { reduceMotion = ev.matches; });
    }

    var dobles = G.modalidad === 'DOBLES';
    var pasos = G.pasos || [];
    var fiestaId = root.getAttribute('data-fiesta-id') || 'default';
    var STORAGE_KEY = 'fiesta:ruleta:step:' + fiestaId;

    var PHASES = {
        capitan: 'Primeros integrantes',
        companero: 'Compañeros',
        grupo: 'Reparto de grupos',
        llave: 'Cruces de la llave'
    };

    var state = {
        i: 0,
        running: false,
        spinning: false,
        auto: true,
        sound: true,
        skipping: false,
        skipStep: false,
        waiting: null,
        cancelSpin: null,
        currentTargetIndex: 0,
        revealed: {},
        placed: {},
        captain: {}
    };

    // ------------------------------------------------------------------
    //  Session persistence
    // ------------------------------------------------------------------
    function loadSavedStep() {
        try {
            var raw = window.sessionStorage.getItem(STORAGE_KEY);
            if (!raw) { return 0; }
            var n = parseInt(raw, 10);
            return (n > 0 && n < pasos.length) ? n : 0;
        } catch (e) {
            return 0;
        }
    }
    function saveStep(step) {
        try {
            if (step > 0 && step < pasos.length) {
                window.sessionStorage.setItem(STORAGE_KEY, String(step));
            } else {
                window.sessionStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) { /* storage unavailable */ }
    }
    function clearSavedStep() {
        try { window.sessionStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignore */ }
    }

    // ------------------------------------------------------------------
    //  Cancellable waits & timers
    // ------------------------------------------------------------------
    var activeWaits = [];
    var confettiTimer = null;
    var endOverlayTimer = null;
    var skipConfirmTimer = null;
    var skipConfirming = false;

    function removeWait(entry) {
        var idx = activeWaits.indexOf(entry);
        if (idx !== -1) { activeWaits.splice(idx, 1); }
    }
    function wait(ms) {
        if (state.skipping || state.skipStep || ms <= 0) { return Promise.resolve(); }
        return new Promise(function (resolve) {
            var settled = false;
            var timerId = null;
            var entry = {
                cancel: function () {
                    if (settled) { return; }
                    settled = true;
                    clearTimeout(timerId);
                    removeWait(entry);
                    resolve();
                }
            };
            timerId = setTimeout(function () {
                if (settled) { return; }
                settled = true;
                removeWait(entry);
                resolve();
            }, ms);
            activeWaits.push(entry);
        });
    }
    function flushWaits() {
        activeWaits.slice().forEach(function (entry) { entry.cancel(); });
    }

    // ------------------------------------------------------------------
    //  Helpers & Dynamic Geometry
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
    function shuffle(xs) {
        var a = xs.slice();
        for (var k = a.length - 1; k > 0; k--) {
            var j = Math.floor(Math.random() * (k + 1));
            var t = a[k]; a[k] = a[j]; a[j] = t;
        }
        return a;
    }
    function announce(text) {
        if (!ui.live) { return; }
        ui.live.textContent = '';
        if (text) {
            requestAnimationFrame(function () { ui.live.textContent = text; });
        }
    }

    function getRowHeight() {
        var first = ui.strip && ui.strip.firstElementChild;
        if (first && first.offsetHeight > 0) {
            return first.offsetHeight;
        }
        return parseFloat(getComputedStyle(ui.reel).getPropertyValue('--row')) || 72;
    }

    function syncStripPosition() {
        if (state.spinning || !ui.strip || ui.strip.children.length === 0) { return; }
        var row = getRowHeight();
        var offset = row - state.currentTargetIndex * row;
        ui.strip.style.transform = 'translateY(' + offset + 'px)';
    }

    function syncOverlayInert() {
        var hasOverlay = !ui.intro.hidden || !ui.countdown.hidden || !ui.end.hidden;
        if (ui.bar) { ui.bar.inert = hasOverlay; }
        if (ui.main) { ui.main.inert = hasOverlay; }
    }

    function setSkipStepEnabled(on) {
        if (!ui.skipStep) { return; }
        ui.skipStep.disabled = !on;
    }

    // ------------------------------------------------------------------
    //  Sound (WebAudio, synthesized — no external files)
    // ------------------------------------------------------------------
    var audio = null;
    var tensionNodes = null;

    function ctx() {
        if (!state.sound) { return null; }
        if (!audio) {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) { return null; }
            try { audio = new AC(); } catch (e) { return null; }
        }
        if (audio.state === 'suspended') { audio.resume().catch(function () {}); }
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

    function stopTensionAudio(immediate) {
        if (!tensionNodes) { return; }
        var nodes = tensionNodes;
        tensionNodes = null;
        try {
            var now = nodes.ctx.currentTime;
            var fade = immediate ? 0.012 : 0.025;
            nodes.gain.gain.cancelScheduledValues(now);
            nodes.gain.gain.setValueAtTime(Math.max(nodes.gain.gain.value, 0.0001), now);
            nodes.gain.gain.exponentialRampToValueAtTime(0.0001, now + fade);
            nodes.osc.stop(now + fade + 0.02);
            nodes.sub.stop(now + fade + 0.02);
        } catch (e) { /* already stopped */ }
    }

    function startTensionAudio(highDrama) {
        stopTensionAudio(true);
        var a = ctx();
        if (!a) { return; }
        var now = a.currentTime;
        var osc = a.createOscillator();
        var sub = a.createOscillator();
        var filter = a.createBiquadFilter();
        var gain = a.createGain();

        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(58, now);
        sub.type = 'sine';
        sub.frequency.setValueAtTime(42, now);

        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(140, now);
        filter.Q.setValueAtTime(2.5, now);

        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(highDrama ? 0.022 : 0.014, now + 0.15);

        osc.connect(filter);
        sub.connect(filter);
        filter.connect(gain).connect(a.destination);

        osc.start(now);
        sub.start(now);

        tensionNodes = {
            ctx: a, osc: osc, sub: sub, filter: filter, gain: gain,
            highDrama: !!highDrama, lastBeat: 0
        };
    }

    function updateTensionAudio(level, nowMs) {
        if (!tensionNodes || !state.sound) {
            if (!state.sound) { stopTensionAudio(true); }
            return;
        }
        var a = tensionNodes.ctx;
        var t = a.currentTime;
        var clamped = Math.max(0, Math.min(1, level));
        var maxGain = tensionNodes.highDrama ? 0.075 : 0.042;
        tensionNodes.osc.frequency.setTargetAtTime(58 + clamped * 56, t, 0.06);
        tensionNodes.sub.frequency.setTargetAtTime(42 + clamped * 22, t, 0.06);
        tensionNodes.filter.frequency.setTargetAtTime(140 + clamped * 360, t, 0.06);
        tensionNodes.gain.gain.setTargetAtTime(0.012 + clamped * maxGain, t, 0.05);

        // Subtle heartbeat lub-dub pulse accelerating as tension rises
        var beatInterval = 420 - clamped * 170;
        if (nowMs - tensionNodes.lastBeat >= beatInterval) {
            tensionNodes.lastBeat = nowMs;
            var hbGain = (tensionNodes.highDrama ? 0.075 : 0.045) * (0.5 + clamped * 0.5);
            tone(62 + clamped * 14, 0.085, 'sine', hbGain, 0);
            tone(52 + clamped * 10, 0.075, 'sine', hbGain * 0.72, 0.11);
        }
    }

    var sfx = {
        tick: function (tension) {
            var a = ctx();
            if (!a) { return; }
            var t = tension || 0;
            // Mechanical wheel peg: brighter when fast, heavier/sharper as it slows
            var freq = (1550 - t * 680) + (Math.random() * 140 - 70);
            var dur = 0.026 + t * 0.022;
            var gain = 0.024 + t * 0.028;
            tone(freq, dur, 'triangle', gain);
            if (t > 0.55) {
                tone(180 - t * 50, 0.032, 'sine', 0.022 + t * 0.025);
            }
        },
        impact: function (isPair) {
            var a = ctx();
            if (!a) { return; }
            var now = a.currentTime;
            // Sub-bass lock-in thump
            var sub = a.createOscillator();
            var g = a.createGain();
            sub.type = 'sine';
            sub.frequency.setValueAtTime(125, now);
            sub.frequency.exponentialRampToValueAtTime(40, now + 0.22);
            g.gain.setValueAtTime(0.0001, now);
            g.gain.exponentialRampToValueAtTime(isPair ? 0.16 : 0.12, now + 0.012);
            g.gain.exponentialRampToValueAtTime(0.0001, now + 0.26);
            sub.connect(g).connect(a.destination);
            sub.start(now);
            sub.stop(now + 0.28);

            if (isPair) {
                [523.25, 659.25, 783.99, 1046.5].forEach(function (f, k) {
                    tone(f, 0.34, 'triangle', 0.08, 0.02 + k * 0.065);
                });
            } else {
                tone(587.33, 0.2, 'triangle', 0.09, 0.01);
                tone(880, 0.32, 'triangle', 0.085, 0.08);
            }
        },
        fanfare: function () {
            [523.25, 659.25, 783.99, 1046.5, 783.99, 1046.5].forEach(function (f, k) {
                tone(f, 0.38, 'triangle', 0.085, k * 0.125);
            });
        }
    };

    // ------------------------------------------------------------------
    //  Confetti (tracked timer so bursts never prematurely clear)
    // ------------------------------------------------------------------
    function confetti(amount) {
        if (reduceMotion || state.skipping) { return; }
        var n = amount || 36;
        for (var k = 0; k < n; k++) {
            var p = el('span', 'rt-confetti__piece rt-confetti__piece--' + (k % 4));
            p.style.setProperty('--x', (Math.random() * 100).toFixed(1) + '%');
            p.style.setProperty('--dx', ((Math.random() - 0.5) * 260).toFixed(0) + 'px');
            p.style.setProperty('--r', (Math.random() * 720 - 360).toFixed(0) + 'deg');
            p.style.setProperty('--d', (950 + Math.random() * 900).toFixed(0) + 'ms');
            p.style.setProperty('--delay', (Math.random() * 180).toFixed(0) + 'ms');
            ui.confetti.appendChild(p);
        }
        if (confettiTimer) { clearTimeout(confettiTimer); }
        confettiTimer = setTimeout(function () {
            confettiTimer = null;
            ui.confetti.textContent = '';
        }, 2300);
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

    function fill(slot, text, silent) {
        slot.textContent = text;
        slot.classList.add('is-filled');
        if (!silent && !state.skipping && !reduceMotion) {
            slot.classList.remove('is-pop');
            void slot.offsetWidth;
            slot.classList.add('is-pop');
        }
        var host = slot.closest('.rt-pair, .rt-match, .rt-group');
        if (host && !silent && !state.skipping) {
            host.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
        }
    }

    // ------------------------------------------------------------------
    //  Pool & Live Counter (ID-safe to prevent name collisions)
    // ------------------------------------------------------------------
    function pool(paso) {
        if (paso.tipo === 'capitan' || paso.tipo === 'companero') {
            return Object.keys(G.participantes)
                .filter(function (id) { return !state.revealed[id]; })
                .map(function (id) { return { id: String(id), label: nombre(id) }; });
        }
        return Object.keys(G.equipos)
            .filter(function (n) { return !state.placed[n]; })
            .map(function (n) { return { id: String(n), label: equipoLabel(n) }; });
    }

    function winnerOf(paso) {
        if (paso.tipo === 'capitan' || paso.tipo === 'companero') {
            return { id: String(paso.participante), label: nombre(paso.participante) };
        }
        return { id: String(paso.equipo), label: equipoLabel(paso.equipo) };
    }

    function updatePoolPill(remaining) {
        if (!ui.poolPill || !ui.poolCount) { return; }
        if (remaining <= 0) {
            ui.poolPill.hidden = true;
            return;
        }
        ui.poolPill.hidden = false;
        ui.poolPill.classList.toggle('is-critical', remaining <= 3);
        ui.poolCount.textContent = remaining === 1
            ? 'Último en el bolillero'
            : 'Quedan ' + remaining + ' en el bolillero';
    }

    // ------------------------------------------------------------------
    //  Doubles Pairing Suspense Banner
    // ------------------------------------------------------------------
    function showMatchupSuspense(paso) {
        if (!ui.matchup) { return; }
        if (paso.tipo !== 'companero') {
            ui.matchup.hidden = true;
            ui.matchup.classList.remove('is-suspense', 'is-revealed');
            return;
        }
        ui.matchupCap.textContent = nombre(state.captain[paso.equipo]);
        ui.matchupSep.textContent = '+';
        ui.matchupPartner.textContent = '¿???';
        ui.matchup.classList.remove('is-revealed');
        ui.matchup.classList.add('is-suspense');
        ui.matchup.hidden = false;
    }

    function revealMatchupPartner(paso) {
        if (!ui.matchup || paso.tipo !== 'companero') { return; }
        ui.matchupSep.textContent = '&';
        ui.matchupPartner.textContent = nombre(paso.participante);
        ui.matchup.classList.remove('is-suspense');
        ui.matchup.classList.add('is-revealed');
    }

    // ------------------------------------------------------------------
    //  High-Tension Reel (rAF-driven, dynamic ROW, near-miss curve)
    // ------------------------------------------------------------------
    function makeReelItem(text, extraClass) {
        var cls = 'rt-reel__item' + (extraClass ? ' ' + extraClass : '');
        if (text.length > 22) { cls += ' is-long'; }
        if (text.length > 34) { cls += ' is-xlong'; }
        var item = el('div', cls);
        item.appendChild(el('span', 'rt-reel__text', text));
        return item;
    }

    function spin(candidates, winner, opts) {
        ui.reel.classList.remove('is-landed', 'is-impact');
        root.classList.remove('is-high-tension');
        ui.strip.textContent = '';
        stopTensionAudio(true);

        if (state.skipping || state.skipStep || reduceMotion || candidates.length <= 1) {
            ui.strip.appendChild(makeReelItem(winner.label, 'is-winner'));
            state.currentTargetIndex = 0;
            var rowImmediate = getRowHeight();
            ui.strip.style.transform = 'translateY(' + rowImmediate + 'px)';
            ui.reel.classList.add('is-landed');
            return Promise.resolve();
        }

        // Filter decoys by participant/team ID so duplicate display names never break the pool
        var others = candidates.filter(function (c) { return c.id !== winner.id; });
        if (others.length === 0) { others = [winner]; }

        var highDrama = opts.isLast || opts.isPartner || opts.remaining <= 2;
        var length = opts.isLast ? 44 : (highDrama ? 38 : 28 + Math.min(8, candidates.length));
        var items = [];
        var bag = [];
        for (var k = 0; k < length - 2; k++) {
            if (bag.length === 0) { bag = shuffle(others); }
            items.push(bag.pop().label);
        }

        // Near-miss: ensure the item immediately before winner is a real decoy
        var decoyBefore = others[Math.floor(Math.random() * others.length)].label;
        if (items.length > 0) { items[items.length - 1] = decoyBefore; }
        items.push(winner.label);
        items.push(others[Math.floor(Math.random() * others.length)].label);
        items.unshift(others[Math.floor(Math.random() * others.length)].label);

        var targetIndex = items.length - 2;
        state.currentTargetIndex = targetIndex;

        items.forEach(function (text, idx) {
            ui.strip.appendChild(makeReelItem(text, idx === targetIndex ? 'is-target' : ''));
        });

        var duration = opts.isLast
            ? 4700
            : (opts.isPartner
                ? 3800 + Math.random() * 450
                : (opts.remaining <= 2 ? 3600 + Math.random() * 400 : 2450 + Math.random() * 550));

        // 120ms dramatic silence window right before lock-in
        var silenceStartT = Math.max(0.90, 1 - (135 / duration));

        return new Promise(function (resolve) {
            state.spinning = true;
            setSkipStepEnabled(true);
            var startTime = null;
            var rafId = null;
            var lastIdx = -1;
            var tensionStarted = false;
            var silenceTriggered = false;
            var finished = false;

            function finishSpin(aborted) {
                if (finished) { return; }
                finished = true;
                if (rafId) { cancelAnimationFrame(rafId); }
                state.spinning = false;
                state.cancelSpin = null;
                setSkipStepEnabled(false);
                stopTensionAudio(true);
                root.classList.remove('is-high-tension');

                var row = getRowHeight();
                ui.strip.style.transform = 'translateY(' + (row - targetIndex * row) + 'px)';
                ui.reel.classList.add('is-landed');
                if (!aborted && !reduceMotion) {
                    ui.reel.classList.remove('is-impact');
                    void ui.reel.offsetWidth;
                    ui.reel.classList.add('is-impact');
                }
                resolve();
            }

            state.cancelSpin = function () { finishSpin(true); };

            function frame(now) {
                if (finished) { return; }
                if (startTime === null) { startTime = now; }
                var elapsed = now - startTime;
                var t = Math.min(1, elapsed / duration);

                // Two-stage high-tension curve:
                // 0..0.94: heavy deceleration crawling past final 3-4 names & decoy
                // 0.94..1.0: crisp magnetic lock-in snap onto the winner
                var pos;
                if (t <= 0.94) {
                    var u = t / 0.94;
                    var eased = 1 - Math.pow(1 - u, 2.55);
                    pos = eased * (targetIndex - 0.14);
                } else {
                    var s = (t - 0.94) / 0.06;
                    pos = (targetIndex - 0.14) + 0.14 * (1 - Math.pow(1 - s, 3));
                }

                // Dynamic row height on every frame (immune to fullscreen/resize mid-spin)
                var row = getRowHeight();
                ui.strip.style.transform = 'translateY(' + (row - pos * row) + 'px)';

                // Mechanical tick synchronized to every item crossing center marker
                var idx = Math.round(pos);
                if (idx !== lastIdx) {
                    lastIdx = idx;
                    if (!silenceTriggered) {
                        sfx.tick(Math.max(0, (t - 0.35) / 0.65));
                    }
                }

                // Rising tension phase (final ~38% of spin)
                if (t >= 0.62 && !silenceTriggered) {
                    if (!tensionStarted) {
                        tensionStarted = true;
                        root.classList.add('is-high-tension');
                        startTensionAudio(highDrama);
                    }
                    var tensionLevel = (t - 0.62) / Math.max(0.05, silenceStartT - 0.62);
                    updateTensionAudio(tensionLevel, now);
                }

                // 120ms dramatic silence right before lock-in impact
                if (t >= silenceStartT && !silenceTriggered) {
                    silenceTriggered = true;
                    stopTensionAudio(false);
                }

                if (t >= 1) {
                    finishSpin(false);
                    return;
                }
                rafId = requestAnimationFrame(frame);
            }

            rafId = requestAnimationFrame(frame);
        });
    }

    // ------------------------------------------------------------------
    //  Steps
    // ------------------------------------------------------------------
    function captionFor(paso, isLast) {
        if (isLast && !state.skipping) { return 'Y el último lugar es para…'; }
        switch (paso.tipo) {
            case 'capitan': return 'Pareja ' + paso.equipo + ': ¿quién la arranca?';
            case 'companero': return 'Pareja ' + paso.equipo + ': ¿quién se suma?';
            default: return dobles ? '¿Qué pareja sale ahora?' : '¿Quién sale ahora?';
        }
    }

    function applyReveal(paso, silent) {
        switch (paso.tipo) {
            case 'capitan':
                state.revealed[paso.participante] = true;
                state.captain[paso.equipo] = paso.participante;
                fill(board.pairs[paso.equipo].s1, nombre(paso.participante), silent);
                board.pairs[paso.equipo].card.classList.add('is-started');
                return nombre(paso.participante) + ' abre la pareja ' + paso.equipo;
            case 'companero':
                state.revealed[paso.participante] = true;
                fill(board.pairs[paso.equipo].s2, nombre(paso.participante), silent);
                board.pairs[paso.equipo].card.classList.add('is-complete');
                return nombre(state.captain[paso.equipo]) + ' & ' + nombre(paso.participante);
            case 'grupo':
                state.placed[paso.equipo] = true;
                var li = el('li', 'rt-group__item');
                board.groups[paso.grupo].list.appendChild(li);
                fill(li, equipoLabel(paso.equipo), silent);
                return equipoLabel(paso.equipo) + ' → Grupo ' + letra(paso.grupo);
            case 'llave':
                state.placed[paso.equipo] = true;
                var cruce = board.bracket[paso.partido];
                fill(cruce[paso.lado], equipoLabel(paso.equipo), silent);
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
        state.skipStep = false;
        var paso = pasos[state.i];
        var isLast = state.i === pasos.length - 1;
        var phaseChanged = state.i === 0 || pasos[state.i - 1].tipo !== paso.tipo;
        var candidates = pool(paso);
        updateHud();
        updatePoolPill(candidates.length);

        if (phaseChanged && !state.skipping) {
            if (ui.matchup) { ui.matchup.hidden = true; }
            root.classList.add('is-phase-change');
            ui.caption.textContent = PHASES[paso.tipo];
            ui.result.textContent = '';
            setSkipStepEnabled(true);
            await wait(1200);
            setSkipStepEnabled(false);
            root.classList.remove('is-phase-change');
        }

        ui.caption.textContent = captionFor(paso, isLast);
        ui.result.textContent = '';
        showMatchupSuspense(paso);
        root.classList.add('is-spinning');

        await spin(candidates, winnerOf(paso), {
            isLast: isLast,
            isPartner: paso.tipo === 'companero',
            remaining: candidates.length
        });

        root.classList.remove('is-spinning', 'is-high-tension');

        // Hold ~280ms of suspense before snapping doubles matchup banner
        if (paso.tipo === 'companero' && !state.skipping && !state.skipStep && !reduceMotion) {
            await wait(280);
        }
        revealMatchupPartner(paso);

        var text = applyReveal(paso, false);
        ui.result.textContent = text;
        if (!state.skipping) { announce(text); }

        state.i++;
        saveStep(state.i);
        updateHud();
        updatePoolPill(Math.max(0, candidates.length - 1));

        if (!state.skipping) {
            sfx.impact(paso.tipo === 'companero');
            if (paso.tipo === 'companero') { confetti(34); }
            if (isLast) { confetti(95); }
        }
        state.skipStep = false;
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
        if (state.running) { return; }
        state.running = true;
        while (state.i < pasos.length) {
            await playStep();
            if (state.i >= pasos.length) { break; }
            if (state.skipping) { continue; }
            if (state.auto) { await wait(1450); }
            if (!state.auto && !state.skipping) { await waitForNext(); }
        }
        state.running = false;
        finish();
    }

    function finish() {
        resetSkipConfirm();
        stopTensionAudio(true);
        setSkipStepEnabled(false);
        clearSavedStep();
        ui.next.hidden = true;
        if (ui.poolPill) { ui.poolPill.hidden = true; }
        ui.caption.textContent = '¡Listo!';
        updateHud();
        if (!state.skipping) {
            sfx.fanfare();
            confetti(120);
            announce('Sorteo completo.');
        } else {
            announce('Sorteo completo.');
        }
        if (endOverlayTimer) { clearTimeout(endOverlayTimer); }
        endOverlayTimer = setTimeout(function () {
            endOverlayTimer = null;
            ui.end.hidden = false;
            syncOverlayInert();
            var primaryLink = ui.end.querySelector('a');
            if (primaryLink) { primaryLink.focus(); }
        }, state.skipping ? 0 : 1400);
    }

    async function countdown() {
        if (reduceMotion || state.skipping) { return; }
        ui.countdown.hidden = false;
        syncOverlayInert();
        for (var n = 3; n >= 1; n--) {
            if (state.skipping || state.skipStep) { break; }
            ui.countdownNum.textContent = String(n);
            ui.countdownNum.classList.remove('is-pop');
            void ui.countdownNum.offsetWidth;
            ui.countdownNum.classList.add('is-pop');
            tone(n === 1 ? 880 : 440, 0.18, 'triangle', 0.08);
            await wait(780);
            if (state.skipping || state.skipStep) { break; }
        }
        state.skipStep = false;
        ui.countdown.hidden = true;
        syncOverlayInert();
    }

    function resetSkipConfirm() {
        skipConfirming = false;
        if (skipConfirmTimer) { clearTimeout(skipConfirmTimer); skipConfirmTimer = null; }
        if (ui.skip) { ui.skip.classList.remove('is-confirming'); }
        if (ui.skipLabel) { ui.skipLabel.textContent = 'Saltar todo'; }
    }

    function updateIntroControls() {
        var saved = loadSavedStep();
        if (ui.resume && saved > 0) {
            ui.resume.hidden = false;
            if (ui.resumeLabel) {
                ui.resumeLabel.textContent = 'Continuar (paso ' + saved + ' / ' + pasos.length + ')';
            }
            if (ui.startLabel) { ui.startLabel.textContent = 'Empezar de cero'; }
            ui.start.classList.remove('btn--primary');
            ui.start.classList.add('btn--secondary');
        } else {
            if (ui.resume) { ui.resume.hidden = true; }
            if (ui.startLabel) { ui.startLabel.textContent = 'Empezar el sorteo'; }
            ui.start.classList.add('btn--primary');
            ui.start.classList.remove('btn--secondary');
        }
    }

    function reset(clearStorage) {
        if (clearStorage) { clearSavedStep(); }
        stopTensionAudio(true);
        flushWaits();
        if (state.cancelSpin) { state.cancelSpin(); }
        if (endOverlayTimer) { clearTimeout(endOverlayTimer); endOverlayTimer = null; }
        if (confettiTimer) { clearTimeout(confettiTimer); confettiTimer = null; }
        resetSkipConfirm();

        state.i = 0;
        state.running = false;
        state.spinning = false;
        state.skipping = false;
        state.skipStep = false;
        state.waiting = null;
        state.currentTargetIndex = 0;
        state.revealed = {};
        state.placed = {};
        state.captain = {};

        root.classList.remove('is-spinning', 'is-high-tension', 'is-phase-change');
        ui.reel.classList.remove('is-landed', 'is-impact');
        ui.end.hidden = true;
        ui.countdown.hidden = true;
        ui.next.hidden = true;
        if (ui.poolPill) { ui.poolPill.hidden = true; }
        if (ui.matchup) { ui.matchup.hidden = true; }
        ui.result.textContent = '';
        ui.caption.textContent = '¿Listos?';
        ui.strip.textContent = '';
        ui.confetti.textContent = '';
        setSkipStepEnabled(false);

        buildBoard();
        updateHud();
        updateIntroControls();
        syncOverlayInert();
    }

    async function startFresh() {
        ctx(); // unlock audio within the user gesture
        clearSavedStep();
        ui.intro.hidden = true;
        syncOverlayInert();
        await countdown();
        if (!state.running && state.i < pasos.length) { run(); }
    }

    function resumeSaved() {
        ctx();
        var saved = loadSavedStep();
        ui.intro.hidden = true;
        syncOverlayInert();
        for (var k = 0; k < saved && k < pasos.length; k++) {
            applyReveal(pasos[k], true);
        }
        state.i = saved;
        updateHud();
        if (!state.running && state.i < pasos.length) { run(); }
    }

    function skipCurrentStep() {
        if (state.i >= pasos.length || state.skipping) { return; }
        state.skipStep = true;
        if (state.cancelSpin) { state.cancelSpin(); }
        flushWaits();
        if (state.waiting) { state.waiting(); }
    }

    function executeSkipAll() {
        resetSkipConfirm();
        if (state.i >= pasos.length) { return; }
        if (!ui.intro.hidden) { ui.intro.hidden = true; }
        ui.countdown.hidden = true;
        syncOverlayInert();
        state.skipping = true;
        stopTensionAudio(true);
        if (state.cancelSpin) { state.cancelSpin(); }
        flushWaits();
        if (state.waiting) { state.waiting(); }
        if (!state.running) { run(); }
    }

    // ------------------------------------------------------------------
    //  Controls & Events
    // ------------------------------------------------------------------
    function setPressed(btn, on) {
        if (btn) { btn.setAttribute('aria-pressed', on ? 'true' : 'false'); }
    }

    ui.start.addEventListener('click', startFresh);
    if (ui.resume) { ui.resume.addEventListener('click', resumeSaved); }
    ui.next.addEventListener('click', function () { if (state.waiting) { state.waiting(); } });

    ui.auto.addEventListener('click', function () {
        state.auto = !state.auto;
        setPressed(ui.auto, state.auto);
        if (state.auto && state.waiting) { state.waiting(); }
    });

    ui.sound.addEventListener('click', function () {
        state.sound = !state.sound;
        setPressed(ui.sound, state.sound);
        if (!state.sound) { stopTensionAudio(true); }
    });

    if (ui.skipStep) {
        ui.skipStep.addEventListener('click', skipCurrentStep);
    }

    // Clicking the spinning reel fast-forwards the current spin cleanly
    ui.reel.addEventListener('click', function () {
        if (state.spinning) { skipCurrentStep(); }
    });

    // 2-step confirmation for "Saltar todo"
    ui.skip.addEventListener('click', function () {
        if (state.i >= pasos.length) { return; }
        if (!skipConfirming) {
            skipConfirming = true;
            ui.skip.classList.add('is-confirming');
            if (ui.skipLabel) { ui.skipLabel.textContent = '¿Seguro? Clic de nuevo'; }
            announce('Presioná de nuevo para saltar todo el sorteo.');
            skipConfirmTimer = setTimeout(resetSkipConfirm, 3500);
            return;
        }
        executeSkipAll();
    });

    ui.replay.addEventListener('click', function () {
        reset(true);
        ui.intro.hidden = false;
        syncOverlayInert();
        ui.start.focus();
    });

    ui.fullscreen.addEventListener('click', toggleFullscreen);

    function toggleFullscreen() {
        if (document.fullscreenElement) { document.exitFullscreen(); }
        else if (root.requestFullscreen) { root.requestFullscreen().catch(function () {}); }
    }

    function onViewportChange() {
        setPressed(ui.fullscreen, !!document.fullscreenElement);
        requestAnimationFrame(syncStripPosition);
    }
    document.addEventListener('fullscreenchange', onViewportChange);
    document.addEventListener('webkitfullscreenchange', onViewportChange);
    window.addEventListener('resize', function () { requestAnimationFrame(syncStripPosition); });

    document.addEventListener('keydown', function (ev) {
        var target = ev.target;
        var isFormField = target && target.closest && target.closest('input, textarea, select');
        if (isFormField) { return; }

        var isInteractive = target && target.closest &&
            target.closest('button, a, [role="button"], [tabindex]');

        // Never hijack Enter or Space when focus is on a button, link, or interactive control
        if (ev.key === ' ' || ev.key === 'Enter') {
            if (isInteractive) { return; }
            if (!ui.intro.hidden) {
                ev.preventDefault();
                if (ui.resume && !ui.resume.hidden) { resumeSaved(); } else { startFresh(); }
                return;
            }
            if (state.waiting) { ev.preventDefault(); state.waiting(); return; }
            if (state.spinning && ev.key === ' ') { ev.preventDefault(); skipCurrentStep(); return; }
        } else if (ev.key === 'ArrowRight') {
            if (state.waiting) { ev.preventDefault(); state.waiting(); }
        } else if (ev.key === 's' || ev.key === 'S') {
            if (state.spinning || state.waiting) { ev.preventDefault(); skipCurrentStep(); }
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
    var numEquipos = Object.keys(G.equipos).length;
    var numPart = Object.keys(G.participantes).length;
    ui.introText.textContent = dobles
        ? numPart + ' participantes, ' + numEquipos + ' parejas. Primero salen los primeros integrantes y después sus compañeros.'
        : numPart + ' jugadores. La ruleta los va sacando de a uno.';

    reset(false);
    if (ui.resume && !ui.resume.hidden) {
        ui.resume.focus({ preventScroll: true });
    } else {
        ui.start.focus({ preventScroll: true });
    }
})();
