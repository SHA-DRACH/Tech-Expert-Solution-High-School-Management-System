/**
 * Animated statistic counters. Each element opts in with
 * `data-counter="1500"` and may supply `data-suffix` / `data-prefix`.
 * Animation runs once, when the element scrolls into view.
 */
export function initCounters() {
    const targets = document.querySelectorAll('[data-counter]');

    if (! targets.length) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const format = (el, value) => {
        const decimals = Number(el.dataset.decimals || 0);
        const text = value.toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });

        el.textContent = `${el.dataset.prefix || ''}${text}${el.dataset.suffix || ''}`;
    };

    const run = (el) => {
        const target = Number(el.dataset.counter || 0);
        const duration = Number(el.dataset.duration || 1800);

        if (reduceMotion) {
            format(el, target);

            return;
        }

        const start = performance.now();
        let settled = false;

        const settle = () => {
            if (settled) {
                return;
            }

            settled = true;
            format(el, target);
        };

        const tick = (now) => {
            if (settled) {
                return;
            }

            const progress = Math.min((now - start) / duration, 1);

            if (progress === 1) {
                settle();

                return;
            }

            // easeOutExpo keeps the count lively at the start and settles gently.
            format(el, target * (1 - Math.pow(2, -10 * progress)));

            requestAnimationFrame(tick);
        };

        requestAnimationFrame(tick);

        /*
         * requestAnimationFrame is throttled to a standstill in a background
         * tab, which would freeze the count partway and leave the card showing
         * a number that is simply wrong. These two backstops guarantee the real
         * figure is always what ends up on screen.
         */
        setTimeout(settle, duration + 150);
        document.addEventListener('visibilitychange', settle, { once: true });
    };

    // The markup already carries the real figure. It is never blanked to zero
    // up front: if the observer never fires - a background tab, a browser
    // without IntersectionObserver, JavaScript disabled - the card must still
    // show the true number rather than a permanent nought.
    if (! ('IntersectionObserver' in window) || reduceMotion) {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (! entry.isIntersecting) {
                return;
            }

            observer.unobserve(entry.target);
            format(entry.target, 0);
            run(entry.target);
        });
    }, { threshold: 0.4 });

    targets.forEach((el) => observer.observe(el));
}
