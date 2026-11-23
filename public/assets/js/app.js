/*
 * Global UI behaviour (no dependencies). Loaded with `defer` by the layouts.
 *  - Service worker registration (PWA) and "Install app" button
 *  - Mobile navigation drawer (#nav-toggle / #nav-principal / #nav-overlay)
 *  - Theme toggle (light / dark / system) persisted in localStorage
 *  - Accessible confirm dialog for forms/buttons with data-confirm
 *  - Dismissible flash messages
 */
(function () {
    'use strict';

    var THEME_KEY = 'pp-theme';

    function safeGet(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }

    function safeSet(key, value) {
        try {
            if (value === null) { window.localStorage.removeItem(key); } else { window.localStorage.setItem(key, value); }
        } catch (e) { /* storage unavailable: theme just won't persist */ }
    }

    /* ---------- Service worker ---------- */
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        });
    }

    /* ---------- Install app (Chromium) ---------- */
    (function () {
        var deferred = null;
        var buttons = function () { return document.querySelectorAll('#btn-instalar, [data-install-app]'); };

        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferred = e;
            buttons().forEach(function (b) { b.hidden = false; });
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('#btn-instalar, [data-install-app]');
            if (!btn || !deferred) { return; }
            deferred.prompt();
            deferred.userChoice.finally(function () {
                deferred = null;
                buttons().forEach(function (b) { b.hidden = true; });
            });
        });

        window.addEventListener('appinstalled', function () {
            deferred = null;
            buttons().forEach(function (b) { b.hidden = true; });
        });
    })();

    /* ---------- Navigation drawer ---------- */
    (function () {
        var toggle = document.getElementById('nav-toggle');
        var nav = document.getElementById('nav-principal');
        var overlay = document.getElementById('nav-overlay');
        if (!toggle || !nav) { return; }

        var desktop = window.matchMedia('(min-width: 1024px)');

        function setOpen(open) {
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
            document.body.classList.toggle('nav-abierto', open);
            if (overlay) { overlay.hidden = !open; }
            if (open) {
                var first = nav.querySelector('a, button');
                if (first) { first.focus(); }
            }
        }

        toggle.addEventListener('click', function () {
            setOpen(!nav.classList.contains('is-open'));
        });

        if (overlay) {
            overlay.addEventListener('click', function () { setOpen(false); });
        }

        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () { setOpen(false); });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });

        var onChange = function () { if (desktop.matches) { setOpen(false); } };
        if (desktop.addEventListener) { desktop.addEventListener('change', onChange); }
    })();

    /* ---------- Theme ---------- */
    (function () {
        var root = document.documentElement;

        function current() {
            var forced = root.getAttribute('data-theme');
            if (forced) { return forced; }
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function sync() {
            var theme = current();
            document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
                var dark = theme === 'dark';
                btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
                var label = btn.querySelector('[data-theme-label]');
                if (label) { label.textContent = dark ? 'Tema claro' : 'Tema oscuro'; }
                btn.setAttribute('title', dark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
            });
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-theme-toggle]');
            if (!btn) { return; }
            var next = current() === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            safeSet(THEME_KEY, next);
            sync();
        });

        sync();
    })();

    /* ---------- Confirm dialog ---------- */
    (function () {
        var dialog = null;
        var pending = null;

        function build() {
            dialog = document.createElement('dialog');
            dialog.className = 'dialog';
            dialog.setAttribute('aria-labelledby', 'dialog-title');
            dialog.setAttribute('aria-describedby', 'dialog-text');
            dialog.innerHTML =
                '<form method="dialog">' +
                '<div class="dialog__body">' +
                '<span class="dialog__icon" data-dialog-icon><svg class="icon" aria-hidden="true"><use href="#i-alert"></use></svg></span>' +
                '<div><h2 class="dialog__title" id="dialog-title"></h2><p class="dialog__text" id="dialog-text"></p></div>' +
                '</div>' +
                '<div class="dialog__actions">' +
                '<button class="btn" value="cancel" data-dialog-cancel>Cancelar</button>' +
                '<button class="btn btn--danger" value="ok" data-dialog-ok>Confirmar</button>' +
                '</div>' +
                '</form>';
            document.body.appendChild(dialog);

            dialog.addEventListener('close', function () {
                var job = pending;
                pending = null;
                if (job && dialog.returnValue === 'ok') { job(); }
            });
        }

        function ask(opts, onConfirm) {
            if (typeof HTMLDialogElement === 'undefined') {
                if (window.confirm(opts.text)) { onConfirm(); }
                return;
            }
            if (!dialog) { build(); }
            var danger = opts.tone !== 'info';
            dialog.querySelector('#dialog-title').textContent = opts.title || (danger ? '¿Confirmás esta acción?' : 'Confirmar');
            dialog.querySelector('#dialog-text').textContent = opts.text || '';
            var ok = dialog.querySelector('[data-dialog-ok]');
            ok.textContent = opts.ok || 'Confirmar';
            ok.className = 'btn ' + (danger ? 'btn--danger' : 'btn--primary');
            dialog.querySelector('[data-dialog-icon]').className = 'dialog__icon' + (danger ? '' : ' dialog__icon--info');
            dialog.returnValue = '';
            pending = onConfirm;
            dialog.showModal();
            dialog.querySelector('[data-dialog-cancel]').focus();
        }

        function optionsFrom(el) {
            return {
                text: el.getAttribute('data-confirm'),
                title: el.getAttribute('data-confirm-title'),
                ok: el.getAttribute('data-confirm-ok'),
                tone: el.getAttribute('data-confirm-tone')
            };
        }

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement) || form.dataset.confirmed === '1') { return; }
            var submitter = e.submitter || null;
            var source = submitter && submitter.hasAttribute('data-confirm') ? submitter
                : (form.hasAttribute('data-confirm') ? form : null);
            if (!source) { return; }

            e.preventDefault();
            ask(optionsFrom(source), function () {
                form.dataset.confirmed = '1';
                if (submitter && form.requestSubmit) {
                    form.requestSubmit(submitter);
                } else if (form.requestSubmit) {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
                delete form.dataset.confirmed;
            });
        }, true);

        // Links that need confirmation (rare: GET actions).
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[data-confirm]');
            if (!link) { return; }
            e.preventDefault();
            ask(optionsFrom(link), function () { window.location.href = link.href; });
        });
    })();

    /* ---------- Print buttons ---------- */
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-print]')) { window.print(); }
    });

    /* ---------- Flash messages (floating toasts + auto-dismiss) ---------- */
    (function () {
        function dismissFlash(flash) {
            if (!flash || flash.classList.contains('is-leaving')) { return; }
            flash.classList.add('is-leaving');
            window.setTimeout(function () {
                var stack = flash.parentElement;
                flash.remove();
                if (stack && stack.classList.contains('flash-stack') && stack.children.length === 0) {
                    stack.remove();
                }
            }, 220);
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-dismiss="flash"]');
            if (!btn) { return; }
            dismissFlash(btn.closest('.flash'));
        });

        document.querySelectorAll('.flash-stack .flash').forEach(function (flash) {
            if (flash.classList.contains('flash--error') || flash.classList.contains('flash--warning')) { return; }
            var ms = 4800;
            flash.setAttribute('data-autodismiss', '1');
            flash.style.setProperty('--toast-ms', ms + 'ms');
            var remaining = ms;
            var started = Date.now();
            var timer = window.setTimeout(function () { dismissFlash(flash); }, remaining);

            flash.addEventListener('mouseenter', function () {
                window.clearTimeout(timer);
                remaining = Math.max(400, remaining - (Date.now() - started));
            });
            flash.addEventListener('mouseleave', function () {
                started = Date.now();
                timer = window.setTimeout(function () { dismissFlash(flash); }, remaining);
            });
        });
    })();

    /* ---------- Cursor Spotlight on Interactive Cards ---------- */
    (function () {
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduced) { return; }
        var selector = '.card--interactive, .rk-podium__card, .podio__card, .quick-link, .fiesta-card, a.stat';
        document.addEventListener('pointermove', function (e) {
            if (e.pointerType !== 'mouse') { return; }
            var card = e.target && e.target.closest ? e.target.closest(selector) : null;
            if (!card) { return; }
            var rect = card.getBoundingClientRect();
            card.style.setProperty('--mx', Math.round(e.clientX - rect.left) + 'px');
            card.style.setProperty('--my', Math.round(e.clientY - rect.top) + 'px');
        }, { passive: true });
    })();

    /* ---------- Animated KPI Counters ---------- */
    (function () {
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduced) { return; }
        var nodes = document.querySelectorAll('.stat__num, .rk-podium__score strong');
        nodes.forEach(function (el) {
            var raw = (el.textContent || '').trim();
            if (!/^\d+$/.test(raw)) { return; }
            var target = parseInt(raw, 10);
            if (target <= 0 || target > 999999) { return; }
            var dur = Math.min(520, Math.max(260, target * 14));
            var start = performance.now();
            el.textContent = '0';
            function tick(now) {
                var t = Math.min(1, (now - start) / dur);
                var eased = 1 - Math.pow(1 - t, 3);
                el.textContent = String(Math.round(target * eased));
                if (t < 1) { requestAnimationFrame(tick); } else { el.textContent = raw; }
            }
            requestAnimationFrame(tick);
        });
    })();

    /* ---------- Command Palette (Ctrl+K / Cmd+K) ---------- */
    (function () {
        var triggers = document.querySelectorAll('[data-cmd-open]');
        if (triggers.length === 0) { return; }

        var backdrop = null;
        var input = null;
        var list = null;
        var items = [];
        var filtered = [];
        var activeIdx = 0;

        function buildItems() {
            var out = [];
            var seen = {};
            document.querySelectorAll('#nav-principal .side-nav__link').forEach(function (a) {
                var href = a.getAttribute('href') || '';
                var label = (a.textContent || '').trim();
                var groupEl = a.closest('.side-nav__group');
                var group = groupEl ? (groupEl.querySelector('.side-nav__title')?.textContent || '').trim() : 'Ir a';
                var svg = a.querySelector('svg');
                if (!href || seen[href]) { return; }
                seen[href] = true;
                out.push({
                    label: label,
                    sub: href,
                    tag: group,
                    iconHtml: svg ? svg.outerHTML : '',
                    run: function () { window.location.href = href; }
                });
            });

            out.push({
                label: 'Cambiar tema claro / oscuro',
                sub: 'Alternar apariencia visual',
                tag: 'Acción',
                iconHtml: '<svg class="icon" aria-hidden="true"><use href="#i-moon"></use></svg>',
                run: function () {
                    var btn = document.querySelector('[data-theme-toggle]');
                    if (btn) { btn.click(); }
                }
            });

            return out;
        }

        function ensurePalette() {
            if (backdrop) { return; }
            backdrop = document.createElement('div');
            backdrop.className = 'cmd-backdrop';
            backdrop.hidden = true;
            backdrop.innerHTML =
                '<div class="cmd-dialog" role="dialog" aria-modal="true" aria-label="Navegación rápida">' +
                '  <div class="cmd-head">' +
                '    <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>' +
                '    <input type="search" class="cmd-input" placeholder="Ir a una sección o acción…" autocomplete="off" spellcheck="false">' +
                '    <span class="cmd-esc">ESC</span>' +
                '  </div>' +
                '  <ul class="cmd-list" role="listbox"></ul>' +
                '  <div class="cmd-foot">' +
                '    <span><kbd>↑</kbd> <kbd>↓</kbd> navegar · <kbd>Enter</kbd> abrir</span>' +
                '    <span>Ping Pong · Navegación rápida</span>' +
                '  </div>' +
                '</div>';
            document.body.appendChild(backdrop);

            input = backdrop.querySelector('.cmd-input');
            list = backdrop.querySelector('.cmd-list');

            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) { close(); }
            });

            input.addEventListener('input', function () {
                activeIdx = 0;
                render(input.value);
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (filtered.length > 0) { activeIdx = (activeIdx + 1) % filtered.length; syncActive(); }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (filtered.length > 0) { activeIdx = (activeIdx - 1 + filtered.length) % filtered.length; syncActive(); }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (filtered[activeIdx]) { var act = filtered[activeIdx]; close(); act.run(); }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    close();
                }
            });
        }

        function syncActive() {
            if (!list) { return; }
            var btns = list.querySelectorAll('.cmd-item');
            btns.forEach(function (b, idx) {
                var isAct = idx === activeIdx;
                b.classList.toggle('is-active', isAct);
                b.setAttribute('aria-selected', isAct ? 'true' : 'false');
                if (isAct && b.scrollIntoView) { b.scrollIntoView({ block: 'nearest' }); }
            });
        }

        function render(query) {
            var q = (query || '').trim().toLowerCase();
            filtered = items.filter(function (it) {
                if (!q) { return true; }
                return (it.label + ' ' + it.sub + ' ' + it.tag).toLowerCase().indexOf(q) !== -1;
            });

            list.innerHTML = '';
            if (filtered.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'cmd-empty';
                empty.textContent = 'Sin coincidencias para "' + query + '"';
                list.appendChild(empty);
                return;
            }

            filtered.forEach(function (it, idx) {
                var li = document.createElement('li');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cmd-item' + (idx === activeIdx ? ' is-active' : '');
                btn.setAttribute('role', 'option');
                btn.setAttribute('aria-selected', idx === activeIdx ? 'true' : 'false');
                btn.innerHTML =
                    '<span class="cmd-item__icon" aria-hidden="true">' + it.iconHtml + '</span>' +
                    '<span class="cmd-item__text"><span></span><span class="cmd-item__sub"></span></span>' +
                    '<span class="cmd-item__tag"></span>';
                btn.querySelector('.cmd-item__text > span:first-child').textContent = it.label;
                btn.querySelector('.cmd-item__sub').textContent = it.sub;
                btn.querySelector('.cmd-item__tag').textContent = it.tag;
                btn.addEventListener('mouseenter', function () { activeIdx = idx; syncActive(); });
                btn.addEventListener('click', function () { close(); it.run(); });
                li.appendChild(btn);
                list.appendChild(li);
            });
        }

        function open() {
            ensurePalette();
            items = buildItems();
            activeIdx = 0;
            backdrop.hidden = false;
            input.value = '';
            render('');
            window.setTimeout(function () { input.focus(); }, 10);
        }

        function close() {
            if (backdrop) { backdrop.hidden = true; }
        }

        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-cmd-open]')) { open(); }
        });

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                if (backdrop && !backdrop.hidden) { close(); } else { open(); }
            }
        });
    })();
})();
