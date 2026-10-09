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
