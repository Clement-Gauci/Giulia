import { Controller } from '@hotwired/stimulus';

// Compteur de caractères pour le message de contact (purement cosmétique :
// la longueur est garantie côté serveur par le Validator).
export default class extends Controller {
    static targets = ['input', 'out'];
    static values = { max: { type: Number, default: 600 } };

    connect() {
        this.update();
    }

    update() {
        const n = this.hasInputTarget ? this.inputTarget.value.length : 0;
        if (this.hasOutTarget) this.outTarget.textContent = n + '/' + this.maxValue;
    }
}
