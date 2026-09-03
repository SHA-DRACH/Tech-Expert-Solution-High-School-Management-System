import Swiper from 'swiper/bundle';

/** Full-screen hero slider on the public homepage. */
export function initHeroSlider() {
    const el = document.querySelector('[data-hero-slider]');

    if (! el) {
        return;
    }

    const slides = el.querySelectorAll('.swiper-slide');

    new Swiper(el, {
        loop: slides.length > 1,
        speed: 900,
        effect: 'fade',
        fadeEffect: { crossFade: true },
        autoplay: slides.length > 1 ? { delay: 6500, disableOnInteraction: false } : false,
        navigation: {
            nextEl: el.querySelector('.swiper-button-next'),
            prevEl: el.querySelector('.swiper-button-prev'),
        },
        pagination: {
            el: el.querySelector('.swiper-pagination'),
            clickable: true,
        },
        a11y: {
            prevSlideMessage: 'Previous slide',
            nextSlideMessage: 'Next slide',
        },
    });
}

/** Generic carousels: teachers, testimonials, gallery strips. */
export function initContentSliders() {
    document.querySelectorAll('[data-slider]').forEach((el) => {
        const perView = Number(el.dataset.perView || 3);
        const autoplay = el.dataset.autoplay !== 'false';

        new Swiper(el, {
            loop: el.querySelectorAll('.swiper-slide').length > perView,
            spaceBetween: Number(el.dataset.gap || 24),
            speed: 650,
            autoplay: autoplay
                ? { delay: 4500, disableOnInteraction: false, pauseOnMouseEnter: true }
                : false,
            navigation: {
                nextEl: el.querySelector('.swiper-button-next'),
                prevEl: el.querySelector('.swiper-button-prev'),
            },
            pagination: el.querySelector('.swiper-pagination')
                ? { el: el.querySelector('.swiper-pagination'), clickable: true }
                : false,
            breakpoints: {
                0: { slidesPerView: 1 },
                576: { slidesPerView: Math.min(2, perView) },
                992: { slidesPerView: Math.min(3, perView) },
                1200: { slidesPerView: perView },
            },
        });
    });
}
