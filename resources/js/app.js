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
 * Testimonials marquee (Home). The rows are static, scrollable strips until
 * this runs; only once the Pause/Play button is wired does the section become
 * .marquee--ready, which is what shows the clones and starts the CSS
 * animation — so motion never exists without a control to stop it. Under
 * reduced motion nothing is started at all: the rows stay static. The button
 * is a plain action button whose label states what it will do next.
 */
const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

for (const marquee of document.querySelectorAll('[data-marquee]')) {
    const toggle = marquee.querySelector('[data-marquee-toggle]');

    if (!toggle || reducedMotionQuery.matches) {
        continue;
    }

    toggle.addEventListener('click', () => {
        const paused = marquee.classList.toggle('marquee--paused');

        toggle.textContent = paused ? toggle.dataset.labelPlay : toggle.dataset.labelPause;
    });

    toggle.hidden = false;
    marquee.classList.add('marquee--ready');
}

/*
 * Practice-area tabs (Home → Offerings): five real tabs, ONE shared panel,
 * dynamic cards. The Blade renders <button role="tab"> controls that all
 * reference the single panel, the panel with the FIRST area's four cards,
 * and the five label/photo sets ONCE as escaped JSON in data-areas — the only
 * data source. Selecting a tab swaps the four cards' labels and photographs
 * in place, synchronises aria-selected and the roving tabindex, points the
 * panel's aria-labelledby at the active tab, resets the card strip to its
 * logical start (scrollLeft 0 is the start edge in both LTR and RTL) and
 * dispatches a resize so the strip's pager and ScrollTrigger re-measure.
 * Nothing is duplicated, no anchors, no hash, no page jump.
 *
 * The design contract is fixed and validated before anything is wired: as
 * many areas as tabs, EXACTLY four items per area, each with a non-empty
 * string label and image, and exactly four server-rendered cards. Without
 * JavaScript — or if any of that fails — the first area stands and the other
 * tabs stay visibly disabled; they are enabled here only after the whole
 * pattern is wired: click/Enter/Space select, Left/Right (mirrored in RTL),
 * Home and End move and select.
 */
const CARDS_PER_AREA = 4;

for (const tabs of document.querySelectorAll('[data-tabs]')) {
    const buttons = Array.from(tabs.querySelectorAll('[data-tab]'));
    const panel = tabs.querySelector('[data-tab-panel]');
    const cards = tabs.querySelector('[data-tab-cards]');
    const track = tabs.querySelector('[data-scroller-track]');

    let areas;

    try {
        areas = JSON.parse(tabs.dataset.areas ?? '');
    } catch {
        continue;
    }

    const isText = (value) => typeof value === 'string' && value.trim() !== '';
    const isArea = (area) => Array.isArray(area?.items)
        && area.items.length === CARDS_PER_AREA
        && area.items.every((item) => isText(item?.label) && isText(item?.image));

    if (
        !panel || !cards || !track
        || buttons.length === 0
        || buttons.some((button) => !button.id)
        || !Array.isArray(areas)
        || areas.length !== buttons.length
        || !areas.every(isArea)
        || cards.children.length !== CARDS_PER_AREA
    ) {
        continue;
    }

    const cardImages = Array.from(cards.children, (card) => card.querySelector('[data-card-image]'));
    const cardLabels = Array.from(cards.children, (card) => card.querySelector('[data-card-label]'));

    if (cardImages.some((image) => !image) || cardLabels.some((label) => !label)) {
        continue;
    }

    const rtl = getComputedStyle(tabs).direction === 'rtl';

    const select = (index, focus = false) => {
        buttons.forEach((button, i) => {
            const active = i === index;

            button.setAttribute('aria-selected', String(active));
            button.tabIndex = active ? 0 : -1;
        });

        panel.setAttribute('aria-labelledby', buttons[index].id);

        areas[index].items.forEach((item, i) => {
            cardImages[i].src = item.image;
            cardLabels[i].textContent = item.label;
        });

        // Back to the logical start, then let the pager and ScrollTrigger
        // re-measure the changed strip.
        track.scrollLeft = 0;
        window.dispatchEvent(new Event('resize'));

        if (focus) {
            buttons[index].focus();
        }
    };

    buttons.forEach((button, i) => {
        button.addEventListener('click', () => select(i));

        button.addEventListener('keydown', (event) => {
            const step = rtl ? -1 : 1;
            const count = buttons.length;
            let next = null;

            if (event.key === 'ArrowRight') {
                next = (i + step + count) % count;
            } else if (event.key === 'ArrowLeft') {
                next = (i - step + count) % count;
            } else if (event.key === 'Home') {
                next = 0;
            } else if (event.key === 'End') {
                next = count - 1;
            }

            if (next !== null) {
                event.preventDefault();
                select(next, true);
            }
        });
    });

    // Fully wired: enable the controls and settle on the first area.
    buttons.forEach((button) => {
        button.disabled = false;
    });

    select(0);
}

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
