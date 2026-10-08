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
