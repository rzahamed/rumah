/*
 * Scroll-triggered reveals for the public site — ONE system, driven by
 * `data-animate` attributes in the shared Blade components, so a section
 * animates the same way on every page that reuses it. GSAP + ScrollTrigger,
 * registered here once (this module is imported by resources/js/app.js).
 *
 * Vocabulary (set on the element to reveal):
 *   data-animate="fade-up"       short fade + 24px rise           (headings, copy)
 *   data-animate="reveal-start"  fade + slide in from the START side  (paired
 *   data-animate="reveal-end"    fade + slide in from the END side     media/copy)
 *   data-animate="scale"         fade + settle from 1.04 → 1       (decorative images)
 *   data-animate="stagger"       the CONTAINER; its descendants marked
 *                                data-animate-item fade-up one after another
 *
 * "Start" and "end" are logical: the offset direction is derived from the
 * document's computed direction, so Arabic mirrors without any markup change.
 *
 * Progressive enhancement, in this order of authority:
 *  1. Nothing is hidden by markup. app.css hides marked elements ONLY inside
 *     `@media (scripting: enabled) and (prefers-reduced-motion: no-preference)`,
 *     with a CSS safety animation that forces them visible after 3s should
 *     this module never run (network error, blocked script). No JavaScript,
 *     or reduced motion, means the page simply renders as authored.
 *  2. This module hands that safety over to GSAP element by element
 *     (`animation: none`), plays each reveal ONCE when the element enters
 *     the viewport as an explicit fromTo — the CSS gate leaves elements at
 *     opacity 0, so the destination must be stated, never read — then clears
 *     its inline styles and marks it `.is-visible` so the CSS keeps it shown
 *     for good: scrolling back never re-hides, and an interrupted tween
 *     settles visible too.
 *  3. Under `prefers-reduced-motion: reduce` it does nothing at all: no
 *     transforms, no tweens; content is already visible via the CSS gate.
 *
 * Only opacity and transform are animated (no layout shifts); offsets are
 * small; nothing pins, scrubs, parallaxes or hijacks scrolling. Form
 * controls, FAQ answers, newsletter inputs, the Cal.com mount, pagination
 * and every other interactive surface are simply never marked.
 */
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

const DURATION = 0.6;
const EASE = 'power2.out';
const RISE = 24;
const SLIDE = 28;
const STAGGER = 0.08;
const START = 'top 85%';

export function initScrollAnimations() {
    const roots = document.querySelectorAll('[data-animate]');

    if (roots.length === 0) {
        return;
    }

    // Reduced motion: the CSS gate has left everything visible; do nothing.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    gsap.registerPlugin(ScrollTrigger);

    // Logical direction: in RTL the "start" side is the right.
    const direction = getComputedStyle(document.documentElement).direction === 'rtl' ? -1 : 1;

    const takeOver = (elements) => {
        elements.forEach((element) => {
            // The CSS safety animation is no longer needed once GSAP owns
            // the element; the tween's from-state hides it until it reveals.
            element.style.animation = 'none';
        });
    };

    const settle = (elements) => () => {
        elements.forEach((element) => {
            element.classList.add('is-visible');
            gsap.set(element, { clearProps: 'opacity,transform' });
        });
    };

    const reveal = (targets, from, triggerElement, options = {}) => {
        const elements = Array.from(targets);

        takeOver(elements);

        gsap.fromTo(
            elements,
            { opacity: 0, x: 0, y: 0, scale: 1, ...from },
            {
                opacity: 1,
                x: 0,
                y: 0,
                scale: 1,
                duration: options.duration ?? DURATION,
                ease: EASE,
                stagger: options.stagger ?? 0,
                overwrite: 'auto',
                scrollTrigger: {
                    trigger: triggerElement,
                    start: START,
                    once: true,
                },
                onComplete: settle(elements),
                onInterrupt: settle(elements),
            },
        );
    };

    roots.forEach((element) => {
        switch (element.dataset.animate) {
            case 'stagger': {
                const items = element.querySelectorAll('[data-animate-item]');

                if (items.length > 0) {
                    reveal(items, { y: RISE }, element, { stagger: STAGGER });
                }

                break;
            }

            case 'reveal-start':
                reveal([element], { x: -SLIDE * direction }, element);
                break;

            case 'reveal-end':
                reveal([element], { x: SLIDE * direction }, element);
                break;

            case 'scale':
                reveal([element], { scale: 1.04, transformOrigin: '50% 50%' }, element, { duration: 0.8 });
                break;

            default:
                reveal([element], { y: RISE }, element);
        }
    });

    // Positions shift as fonts swap and images size themselves; recompute
    // the trigger points once each has settled.
    const refresh = () => ScrollTrigger.refresh();

    window.addEventListener('load', refresh, { once: true });

    if (document.fonts && typeof document.fonts.ready?.then === 'function') {
        document.fonts.ready.then(refresh);
    }
}
