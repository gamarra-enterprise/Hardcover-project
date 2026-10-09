// Marks the page as ready so entrance animations play once, and drives the brand cover.
// The cover lifts away as you scroll: --p goes from 0 (top) to 1 (cover fully scrolled past).
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.documentElement.classList.add('play');

const cover = document.getElementById('cover');

if (cover && !reduced) {
    let frame = 0;

    const update = () => {
        frame = 0;
        const progress = Math.min(1, Math.max(0, window.scrollY / (cover.offsetHeight || 700)));
        cover.style.setProperty('--p', progress.toFixed(4));
    };

    window.addEventListener('scroll', () => { frame ||= requestAnimationFrame(update); }, { passive: true });
    update();
}

// Carousel arrows: scroll by about one card.
document.addEventListener('click', (e) => {
    const arrow = e.target.closest('[data-scroll]');
    if (!arrow) return;
    const track = document.getElementById(arrow.dataset.target);
    track?.scrollBy({ left: Number(arrow.dataset.scroll) * Math.max(280, track.clientWidth * 0.8), behavior: reduced ? 'auto' : 'smooth' });
});

// Mouse drag on carousels, with a little inertia. Touch already scrolls natively.
(() => {
    let el = null, startX = 0, startLeft = 0, lastX = 0, lastT = 0, velocity = 0, moved = 0, raf = 0, dragged = false;

    document.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'mouse' || e.button !== 0) return;
        const track = e.target.closest('.carousel');
        if (!track) return;
        cancelAnimationFrame(raf);
        el = track; startX = lastX = e.clientX; startLeft = track.scrollLeft; lastT = performance.now(); velocity = 0; moved = 0;
    });

    window.addEventListener('pointermove', (e) => {
        if (!el) return;
        const dx = e.clientX - startX;
        moved = Math.max(moved, Math.abs(dx));
        if (moved > 5) {
            el.classList.add('drag');
            el.scrollLeft = startLeft - dx;
            const now = performance.now();
            velocity = ((lastX - e.clientX) / Math.max(1, now - lastT)) * 16;
            lastX = e.clientX; lastT = now;
        }
    });

    window.addEventListener('pointerup', () => {
        if (!el) return;
        const track = el; el = null;
        if (moved <= 5) return;
        dragged = true;
        setTimeout(() => { dragged = false; }, 0);
        const glide = () => {
            track.scrollLeft += velocity; velocity *= 0.94;
            if (Math.abs(velocity) > 0.3) raf = requestAnimationFrame(glide); else track.classList.remove('drag');
        };
        glide();
    });

    // A drag that ends over a card must not open it.
    document.addEventListener('click', (e) => { if (dragged) { e.stopPropagation(); e.preventDefault(); } }, true);
})();

// Scroll-linked reveal: every .rv block fades and slides in as it enters the screen, and back out as it leaves,
// following the scroll in both directions. --sp is the block's progress (0 to 1) and --e its eased value;
// the styles in shop-extra.css turn them into movement. Without JS or with reduced motion they stay at 1 (everything visible).
(() => {
    if (reduced) return;
    const clamp01 = (x) => Math.max(0, Math.min(1, x));
    const easeOut = (x) => 1 - Math.pow(1 - x, 3);
    let items = [], frame = 0;

    const target = (el) => {
        const r = el.getBoundingClientRect(), vh = window.innerHeight || 800;
        return clamp01((vh * 0.88 - r.top) / (r.height + vh * 0.43));
    };

    const step = (item, snap) => {
        const p = clamp01(target(item.el) / 0.86);
        item.cur = snap ? p : item.cur + (p - item.cur) * 0.14;
        item.el.style.setProperty('--sp', item.cur.toFixed(4));
        item.el.style.setProperty('--e', easeOut(item.cur).toFixed(4));
        return Math.abs(p - item.cur) > 0.003;
    };

    const loop = (snap) => {
        let more = false;
        items.forEach((item) => { if (item.el.isConnected && step(item, snap)) more = true; });
        frame = more ? requestAnimationFrame(() => loop(false)) : 0;
    };

    const kick = () => { if (!frame && items.length) frame = requestAnimationFrame(() => loop(false)); };

    const scan = () => {
        cancelAnimationFrame(frame); frame = 0;
        items = [...document.querySelectorAll('.rv')].map((el) => {
            [...el.children].forEach((child, i) => child.style.setProperty('--i', i));
            return { el, cur: 0 };
        });
        rows();
        loop(true);
    };

    // Covers of the shelf appear row by row: number each cover by the row it sits in.
    const rows = () => document.querySelectorAll('.mosaic').forEach((grid) => {
        let row = -1, top = null;
        [...grid.children].forEach((card) => {
            if (top === null || Math.abs(card.offsetTop - top) > 4) { row += 1; top = card.offsetTop; }
            card.style.setProperty('--r', row);
        });
    });

    window.addEventListener('resize', rows);
    window.addEventListener('scroll', kick, { passive: true });
    window.addEventListener('resize', kick);
    document.addEventListener('livewire:navigated', scan);
    scan();
})();

// After changing page or filter in a listing, bring the top of the listing back into view.
window.addEventListener('listing-changed', () => {
    const top = document.getElementById('listing-top');
    if (top && top.getBoundingClientRect().top < 0) {
        top.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
    }
});
