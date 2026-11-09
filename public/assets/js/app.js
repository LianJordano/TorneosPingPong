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

    /* ---------- Flash messages ---------- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-dismiss="flash"]');
        if (!btn) { return; }
        var flash = btn.closest('.flash');
        if (flash) { flash.remove(); }
    });
})();
