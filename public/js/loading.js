/* ====================================================================
 * Global AJAX loading indicator.
 * - Monkey-patches window.fetch (counter-based, multi-request safe).
 * - Hooks jQuery ajaxSend/ajaxComplete.
 * - Top bar (NProgress-style) + delayed center overlay.
 * - Skip background/typeahead requests via SILENT_URL_PATTERNS,
 *   `silent: true` option, hoặc header X-Silent-Request: 1.
 * - Public API: window.AppLoading.{show,hide,addSilentPattern}.
 * ==================================================================== */
(function () {
    'use strict';

    var SILENT_URL_PATTERNS = [
        /\/notifications\/feed/i,
        /\/users\/search/i,
        /\/broadcasting\/auth/i,
    ];
    var DELAY_BEFORE_BAR_MS     = 100;
    var DELAY_BEFORE_OVERLAY_MS = 600;
    var MIN_VISIBLE_MS          = 300;

    var active = 0, startedAt = 0, progress = 0;
    var barTimer = null, overlayTimer = null, hideTimer = null, trickleTimer = null;
    var $bar = null, $overlay = null;

    function ensureMounted() {
        if ($bar) return;
        $bar = document.createElement('div');
        $bar.className = 'app-loading-bar';
        document.body.appendChild($bar);

        $overlay = document.createElement('div');
        $overlay.className = 'app-loading-overlay';
        $overlay.innerHTML =
            '<div class="app-loading-card">' +
                '<div class="app-loading-spinner"></div>' +
                '<div class="app-loading-text">Đang xử lý…</div>' +
            '</div>';
        document.body.appendChild($overlay);
    }

    function setBar(p) { progress = p; if ($bar) $bar.style.width = p + '%'; }

    function trickle() {
        clearTimeout(trickleTimer);
        if (progress < 90) {
            setBar(progress + Math.max(0.6, (90 - progress) * 0.06));
            trickleTimer = setTimeout(trickle, 240);
        }
    }

    function show() {
        active++;
        if (active !== 1) return;
        ensureMounted();
        startedAt = Date.now();
        clearTimeout(hideTimer); hideTimer = null;

        barTimer = setTimeout(function () {
            $bar.classList.remove('is-done');
            $bar.classList.add('is-active');
            setBar(8);
            trickle();
        }, DELAY_BEFORE_BAR_MS);

        overlayTimer = setTimeout(function () {
            $overlay.classList.add('is-active');
        }, DELAY_BEFORE_OVERLAY_MS);
    }

    function hide() {
        if (active === 0) return;
        active--;
        if (active !== 0) return;

        clearTimeout(barTimer);     barTimer = null;
        clearTimeout(overlayTimer); overlayTimer = null;
        clearTimeout(trickleTimer); trickleTimer = null;

        var elapsed = Date.now() - startedAt;
        var visible = $bar && $bar.classList.contains('is-active');
        var remain  = visible ? Math.max(0, MIN_VISIBLE_MS - elapsed) : 0;

        hideTimer = setTimeout(function () {
            if ($overlay) $overlay.classList.remove('is-active');
            if ($bar) {
                setBar(100);
                $bar.classList.add('is-done');
                setTimeout(function () {
                    if (active === 0 && $bar) {
                        $bar.classList.remove('is-active', 'is-done');
                        setBar(0);
                    }
                }, 360);
            }
        }, remain);
    }

    function shouldIgnore(input, init) {
        if (init) {
            if (init.silent === true) return true;
            var h = init.headers;
            if (h) {
                if (typeof Headers !== 'undefined' && h instanceof Headers) {
                    if (h.get('X-Silent-Request')) return true;
                } else if (h['X-Silent-Request'] || h['x-silent-request']) {
                    return true;
                }
            }
        }
        var url = '';
        try { url = (typeof input === 'string') ? input : String(input.url || input); }
        catch (e) { return false; }
        for (var i = 0; i < SILENT_URL_PATTERNS.length; i++) {
            if (SILENT_URL_PATTERNS[i].test(url)) return true;
        }
        return false;
    }

    // ---- Patch fetch ----
    var origFetch = window.fetch ? window.fetch.bind(window) : null;
    if (origFetch) {
        window.fetch = function (input, init) {
            if (shouldIgnore(input, init)) return origFetch(input, init);
            show();
            return origFetch(input, init).then(
                function (res) { hide(); return res; },
                function (err) { hide(); throw err; }
            );
        };
    }

    // ---- Hook jQuery (nếu có) ----
    document.addEventListener('DOMContentLoaded', function () {
        if (! window.jQuery) return;
        jQuery(document)
            .ajaxSend(function (e, jqxhr, settings) {
                if (settings && settings.silent) return;
                if (settings && settings.url && shouldIgnore(settings.url, null)) return;
                jqxhr._appLoadingTracked = true;
                show();
            })
            .ajaxComplete(function (e, jqxhr) {
                if (jqxhr && jqxhr._appLoadingTracked) hide();
            });
    });

    window.AppLoading = {
        show: show,
        hide: hide,
        addSilentPattern: function (re) { SILENT_URL_PATTERNS.push(re); },
    };
})();
