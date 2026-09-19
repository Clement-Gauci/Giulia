import { Controller } from '@hotwired/stimulus';

/*
 * Compte à rebours de l'écran de blocage.
 *
 * Sans JavaScript, l'horloge affiche simplement la valeur rendue par le
 * serveur : elle reste juste, elle ne bouge pas. La pause elle-même est tenue
 * en base, jamais ici — arrêter ce script ne débloque rien.
 */
export default class extends Controller {
    static targets = ['clock', 'retry'];
    static values = { seconds: Number };

    connect() {
        this.remaining = this.secondsValue;
        this.render();

        if (this.remaining > 0) {
            this.timer = setInterval(() => this.tick(), 1000);
        }
    }

    disconnect() {
        clearInterval(this.timer);
    }

    tick() {
        this.remaining -= 1;

        if (this.remaining <= 0) {
            this.remaining = 0;
            clearInterval(this.timer);
        }

        this.render();
    }

    render() {
        if (this.hasClockTarget) {
            const minutes = Math.floor(this.remaining / 60);
            const seconds = this.remaining % 60;
            this.clockTarget.textContent = `${pad(minutes)}:${pad(seconds)}`;
        }

        if (this.hasRetryTarget) {
            this.retryTarget.hidden = this.remaining > 0;
        }
    }
}

function pad(value) {
    return String(value).padStart(2, '0');
}
