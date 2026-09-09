import { Controller } from '@hotwired/stimulus';

/*
 * Les six cases du code de connexion, et le compte à rebours avant renvoi.
 *
 * Le formulaire fonctionne sans ce contrôleur : le gabarit rend alors un champ
 * unique de six chiffres, que le serveur lit de la même façon. Ce qui suit
 * n'est que du confort de saisie.
 */
export default class extends Controller {
    static targets = ['box', 'resend', 'countdown', 'seconds'];
    static values = { resendIn: Number };

    connect() {
        this.boxTargets[0]?.focus();

        if (this.hasResendInValue && this.resendInValue > 0) {
            this.remaining = this.resendInValue;
            this.render();
            this.timer = setInterval(() => this.tick(), 1000);
        }
    }

    disconnect() {
        clearInterval(this.timer);
    }

    // Une case remplie fait glisser le focus vers la suivante.
    advance(event) {
        const box = event.currentTarget;
        box.value = box.value.replace(/\D/g, '').slice(-1);

        if (box.value) {
            this.next(box)?.focus();
        }
    }

    // Retour arrière sur une case vide : on remonte d'un cran.
    back(event) {
        if (event.key !== 'Backspace' || event.currentTarget.value) {
            return;
        }

        const previous = this.previous(event.currentTarget);
        if (previous) {
            event.preventDefault();
            previous.value = '';
            previous.focus();
        }
    }

    // Coller « 204815 » remplit les six cases d'un coup.
    paste(event) {
        const digits = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '');
        if (!digits) {
            return;
        }

        event.preventDefault();
        const start = this.boxTargets.indexOf(event.currentTarget);

        digits.split('').forEach((digit, offset) => {
            const box = this.boxTargets[start + offset];
            if (box) {
                box.value = digit;
            }
        });

        this.boxTargets[Math.min(start + digits.length, this.boxTargets.length - 1)].focus();
    }

    tick() {
        this.remaining -= 1;

        if (this.remaining <= 0) {
            clearInterval(this.timer);
        }

        this.render();
    }

    render() {
        const waiting = this.remaining > 0;

        if (this.hasSecondsTarget) {
            this.secondsTarget.textContent = String(Math.max(0, this.remaining));
        }
        if (this.hasCountdownTarget) {
            this.countdownTarget.hidden = !waiting;
        }
        if (this.hasResendTarget) {
            this.resendTarget.hidden = waiting;
        }
    }

    next(box) {
        return this.boxTargets[this.boxTargets.indexOf(box) + 1];
    }

    previous(box) {
        return this.boxTargets[this.boxTargets.indexOf(box) - 1];
    }
}
