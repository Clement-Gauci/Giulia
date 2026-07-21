import { Controller } from '@hotwired/stimulus';

// Slider horizontal des pizzas : défilement auto (respecte prefers-reduced-motion),
// pause au survol, et boutons précédent/suivant.
export default class extends Controller {
    static targets = ['scroller'];
    static values = { interval: { type: Number, default: 3200 }, step: { type: Number, default: 220 } };

    connect() {
        this.paused = false;
        const el = this.scrollerTarget;
        el.addEventListener('pointerenter', this.pause);
        el.addEventListener('pointerleave', this.resume);
        el.addEventListener('touchstart', this.pause, { passive: true });
        el.addEventListener('touchend', this.resume, { passive: true });
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.timer = setInterval(() => this.tick(), this.intervalValue);
        }
    }

    disconnect() {
        clearInterval(this.timer);
        const el = this.scrollerTarget;
        el.removeEventListener('pointerenter', this.pause);
        el.removeEventListener('pointerleave', this.resume);
        el.removeEventListener('touchstart', this.pause);
        el.removeEventListener('touchend', this.resume);
    }

    pause = () => { this.paused = true; };
    resume = () => { this.paused = false; };

    prev() { this.scrollerTarget.scrollBy({ left: -this.stepValue, behavior: 'smooth' }); }
    next() { this.scrollerTarget.scrollBy({ left: this.stepValue, behavior: 'smooth' }); }

    tick() {
        if (this.paused) return;
        const el = this.scrollerTarget;
        const max = el.scrollWidth - el.clientWidth;
        if (el.scrollLeft >= max - 4) el.scrollTo({ left: 0, behavior: 'smooth' });
        else el.scrollBy({ left: this.stepValue, behavior: 'smooth' });
    }
}
