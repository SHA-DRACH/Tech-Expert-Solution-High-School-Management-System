import './bootstrap';

/*
 * Livewire ships its own Alpine build. Importing the ESM bundle and starting it
 * by hand is the supported way to register Alpine plugins, and it guarantees a
 * single Alpine instance driving the whole interface.
 *
 * The layouts pair this with @livewireScriptConfig instead of @livewireScripts.
 */
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

import AOS from 'aos';
import 'aos/dist/aos.css';
import 'swiper/css/bundle';

import { initCounters } from './modules/counters';
import { initHeroSlider, initContentSliders } from './modules/sliders';
import { initHeader, initScrollProgress } from './modules/header';

/*
 * No Alpine plugins are registered here on purpose. Livewire's ESM bundle
 * already ships Alpine with collapse, focus, intersect and persist. Importing
 * the standalone @alpinejs/* packages pulls a SECOND copy of Alpine into the
 * bundle and registers those magics twice, which throws
 * "Cannot redefine property: $persist" during Livewire.start() — and because
 * that happens before the listener below is attached, every scroll reveal on
 * the page silently stays invisible.
 */

Livewire.start();

document.addEventListener('DOMContentLoaded', () => {
    initHeader();
    initScrollProgress();

    // Sliders must lay out before AOS measures the page. Swiper's fade effect
    // stacks slides absolutely, which changes the document height; measuring
    // first would leave every reveal below the hero anchored to the wrong
    // position and they would never trigger.
    initHeroSlider();
    initContentSliders();

    AOS.init({
        duration: 700,
        easing: 'ease-out-cubic',
        once: true,
        offset: 60,
        disable: () => window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    });

    initCounters();
});

/*
 * Keeping AOS's measurements honest.
 *
 * AOS caches every element's offset when it initialises and only recalculates
 * on resize. Anything that changes the page height afterwards — a web font
 * swapping in, the hero image decoding, an image without fixed dimensions —
 * leaves those offsets pointing at the wrong places, and sections below the
 * fold never trigger. The page then scrolls past permanently invisible
 * content, which is far worse than having no animation at all.
 *
 * `load` alone is not enough: web fonts frequently settle after it.
 */
window.addEventListener('load', () => AOS.refreshHard());

if (document.fonts?.ready) {
    document.fonts.ready.then(() => AOS.refreshHard());
}

// The catch-all: anything that changes the document's height re-measures.
// Debounced, because a font swap fires this several times in a row.
if ('ResizeObserver' in window) {
    let pending;
    let lastHeight = document.body.scrollHeight;

    new ResizeObserver(() => {
        const height = document.body.scrollHeight;

        if (height === lastHeight) {
            return;
        }

        lastHeight = height;

        clearTimeout(pending);
        pending = setTimeout(() => AOS.refreshHard(), 150);
    }).observe(document.body);
}

// Livewire swaps DOM between requests, so anything revealed on scroll has to be
// re-registered once the new markup is in place.
document.addEventListener('livewire:navigated', () => AOS.refreshHard());
