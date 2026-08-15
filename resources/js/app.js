/*
 * Design assets live in resources/images (Home photographs and the exported
 * SVG icons) and are referenced from Blade through Vite::asset(). Globbing
 * them here is what puts them in the build manifest so those references
 * resolve in production. The glob is EAGER on purpose: Vite 8's bundler
 * tree-shakes an unused lazy glob away entirely, and the images then never
 * reach the manifest. Eager imports of image modules resolve to URL strings
 * only — nothing is fetched at runtime by this line.
 */
import.meta.glob(['../images/**'], { eager: true });

import { initScrollAnimations } from './animations';

/*
 * Scroll-triggered reveals for every public page (see animations.js). The
 * entry module is deferred, so the DOM is parsed by the time this runs.
 */
initScrollAnimations();

/*
 * Sub-offerings scroller (Home → Offerings). Progressive enhancement over a
 * native scroll-snap strip that already works by swipe, trackpad and arrow
 * keys: this reveals the pager and lets it page the strip one card at a time.
 * The pager stays hidden unless this code runs AND the strip actually
 * overflows, so no control ever appears that cannot do anything.
 */
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

for (const scroller of document.querySelectorAll('[data-scroller]')) {
    const track = scroller.querySelector('[data-scroller-track]');
    const pager = scroller.querySelector('[data-scroller-pager]');
    const prev = scroller.querySelector('[data-scroller-dir="prev"]');
    const next = scroller.querySelector('[data-scroller-dir="next"]');

    if (!track || !pager || !prev || !next) {
        continue;
    }

    // Reading direction decides which way "next" scrolls; scrollLeft grows
    // negative toward the end in RTL, so distances are compared in absolute.
    const rtl = getComputedStyle(track).direction === 'rtl';

    // One page = one card plus the list's gap. The track's own first child is
    // the <ol> (.scroller__list), so the card and the gap are read from that
    // list — measuring the track's child would page by the whole strip.
    const step = () => {
        const list = track.querySelector('.scroller__list');
        const first = list ? list.querySelector(':scope > :first-child') : null;
        const gap = list ? parseFloat(getComputedStyle(list).columnGap) || 0 : 0;

        return first ? first.getBoundingClientRect().width + gap : track.clientWidth;
    };

    const page = (direction) => {
        track.scrollBy({
            left: direction * step() * (rtl ? -1 : 1),
            behavior: reducedMotion.matches ? 'auto' : 'smooth',
        });
    };

    const sync = () => {
        const max = track.scrollWidth - track.clientWidth;
        const overflows = max > 1;

        // Only an overflowing strip gets the pager and the hidden scrollbar;
        // when everything fits, both controls would be inert, so neither shows.
        scroller.classList.toggle('scroller--enhanced', overflows);
        pager.hidden = !overflows;

        if (!overflows) {
            prev.disabled = true;
            next.disabled = true;

            return;
        }

        // Clamped: Safari reports positions beyond the range during overscroll.
        const position = Math.min(Math.max(Math.abs(track.scrollLeft), 0), max);

        prev.disabled = position <= 1;
        next.disabled = position >= max - 1;
    };

    prev.addEventListener('click', () => page(-1));
    next.addEventListener('click', () => page(1));
    track.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync, { passive: true });

    sync();
}
