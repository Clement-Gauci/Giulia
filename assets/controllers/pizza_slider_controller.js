import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { interval: { type: Number, default: 3200 }, step: { type: Number, default: 220 } };

    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        this.paused = false;
        this.element.addEventListener('pointerenter', this.pause);
        this.element.addEventListener('pointerleave', this.resume);
        this.timer = setInterval(() => this.tick(), this.intervalValue);
    }

    disconnect() {
        clearInterval(this.timer);
        this.element.removeEventListener('pointerenter', this.pause);
        this.element.removeEventListener('pointerleave', this.resume);
    }

    pause = () => { this.paused = true; };
    resume = () => { this.paused = false; };

    tick() {
        if (this.paused) return;
        const el = this.element;
        const max = el.scrollWidth - el.clientWidth;
        if (el.scrollLeft >= max - 4) el.scrollTo({ left: 0, behavior: 'smooth' });
        else el.scrollBy({ left: this.stepValue, behavior: 'smooth' });
    }
}
