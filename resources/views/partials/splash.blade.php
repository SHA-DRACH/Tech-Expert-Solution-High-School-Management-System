{{--
    The NovaxSuites loading splash.

    Written inline rather than in the Vite bundle on purpose: it has to be on
    screen before that bundle has downloaded, or it would flash in after the
    page it is meant to cover.

    Shown while a page loads and while leaving for another page. Never shown
    for a file download, a new tab or a same-page jump - the page does not
    change, so the splash would have nothing to wait for - and it always clears
    itself after a few seconds, so it can never lock anyone out of a page.
    A link or form can opt out with data-no-splash.
--}}
@php $product = config('app.product', 'NovaxSuites'); @endphp

<div id="nx-splash" role="status" aria-live="polite" aria-label="Loading {{ $product }}">
    <div class="nx-spinner" aria-hidden="true">
        @for ($i = 0; $i < 8; $i++)
            <span style="--i: {{ $i }}"></span>
        @endfor
    </div>
    <p class="nx-label">Loading {{ $product }}<span class="nx-dots" aria-hidden="true"></span></p>
</div>

<style>
    #nx-splash {
        position: fixed; inset: 0; z-index: 2147483000;
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 28px;
        background: rgba(38, 38, 38, .68);
        opacity: 1; visibility: visible;
        transition: opacity .28s ease, visibility .28s ease;
    }
    #nx-splash.nx-hidden { opacity: 0; visibility: hidden; pointer-events: none; }

    #nx-splash .nx-spinner { position: relative; width: 84px; height: 84px; animation: nx-spin 1s steps(8) infinite; }
    #nx-splash .nx-spinner span {
        position: absolute; left: 50%; top: 50%; width: 17px; height: 17px; margin: -8.5px 0 0 -8.5px;
        border-radius: 50%; background: #fff;
        transform: rotate(calc(var(--i) * 45deg)) translateY(-32px);
        opacity: calc(1 - var(--i) * .035);
        box-shadow: 0 1px 4px rgba(0, 0, 0, .25);
    }
    #nx-splash .nx-label {
        margin: 0; color: #fff; font: 500 30px/1.2 "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
        letter-spacing: .01em; text-shadow: 0 1px 6px rgba(0, 0, 0, .35); text-align: center; padding: 0 16px;
    }
    #nx-splash .nx-dots::after { content: "..."; display: inline-block; width: 1.2em; text-align: left; animation: nx-ellipsis 1.2s steps(4) infinite; overflow: hidden; vertical-align: bottom; }

    @keyframes nx-spin { to { transform: rotate(360deg); } }
    @keyframes nx-ellipsis { 0% { width: 0; } 100% { width: 1.2em; } }

    @media (max-width: 480px) {
        #nx-splash .nx-label { font-size: 22px; }
        #nx-splash .nx-spinner { transform: scale(.85); }
    }
    @media (prefers-reduced-motion: reduce) {
        #nx-splash .nx-spinner { animation-duration: 2.4s; }
        #nx-splash .nx-dots::after { animation: none; width: 1.2em; }
    }
    @media print { #nx-splash { display: none !important; } }
</style>

<script>
    (function () {
        var splash = document.getElementById('nx-splash');
        if (!splash) return;

        var shownAt = Date.now();
        var safety;

        function hide() {
            clearTimeout(safety);
            // A brief minimum, so a fast page does not flicker the splash.
            var wait = Math.max(0, 250 - (Date.now() - shownAt));
            setTimeout(function () { splash.classList.add('nx-hidden'); }, wait);
        }

        function show() {
            shownAt = Date.now();
            splash.classList.remove('nx-hidden');
            clearTimeout(safety);
            // Never trap anyone: if the next page has not arrived, step aside.
            safety = setTimeout(hide, 8000);
        }

        safety = setTimeout(hide, 8000);

        // Cleared once the page itself is ready, not when every photo and font
        // has arrived: on a slow connection a gallery page would otherwise keep
        // people waiting behind the splash for content they can already use.
        if (document.readyState !== 'loading') {
            hide();
        } else {
            document.addEventListener('DOMContentLoaded', hide);
        }

        // Coming back with the browser's Back button restores the page from
        // cache with the splash still showing; clear it.
        window.addEventListener('pageshow', function (event) { if (event.persisted) hide(); });

        // Every route that answers with a file rather than a page. Checked
        // against the full route list: downloads, CSV exports, question papers
        // and the mark-upload template.
        var fileLike = /\/(download|exports?|question|template)(\/|$)|\.(xlsx|csv|pdf|docx?)$/i;

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            var link = event.target.closest ? event.target.closest('a[href]') : null;
            if (!link || link.hasAttribute('data-no-splash') || link.hasAttribute('download')) return;
            if (link.target && link.target !== '_self') return;

            var href = link.getAttribute('href');
            if (!href || href.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(href)) return;

            var url;
            try { url = new URL(link.href, window.location.href); } catch (e) { return; }

            if (url.origin !== window.location.origin) return;
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
            if (fileLike.test(url.pathname)) return;

            show();
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            // Wait a tick: a confirm() or a validation handler may cancel it.
            setTimeout(function () {
                if (event.defaultPrevented || form.hasAttribute('data-no-splash')) return;
                if (form.target && form.target !== '_self') return;
                var path;
                try { path = new URL(form.action || window.location.href, window.location.href).pathname; } catch (e) { path = ''; }
                if (fileLike.test(path)) return;
                show();
            }, 0);
        });
    })();
</script>
