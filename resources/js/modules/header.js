/**
 * Sticky header shadow. The header gains `is-stuck` once the page has scrolled
 * past a few pixels, which deepens its shadow and tightens its padding.
 */
export function initHeader() {
    const header = document.querySelector('.site-header');

    if (! header) {
        return;
    }

    const toggleStuck = () => {
        header.classList.toggle('is-stuck', window.scrollY > 12);
    };

    toggleStuck();
    window.addEventListener('scroll', toggleStuck, { passive: true });
}

/**
 * Thin reading-progress bar across the top of long public pages.
 */
export function initScrollProgress() {
    const bar = document.querySelector('[data-scroll-progress]');

    if (! bar) {
        return;
    }

    const update = () => {
        const scrollable = document.documentElement.scrollHeight - window.innerHeight;
        const progress = scrollable > 0 ? window.scrollY / scrollable : 0;

        bar.style.transform = `scaleX(${Math.min(progress, 1)})`;
    };

    update();
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update, { passive: true });
}
