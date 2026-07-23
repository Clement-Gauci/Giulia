import { Controller } from '@hotwired/stimulus';

/*
 * Soumission classique (sans Turbo) : le navigateur recharge la page.
 * On affiche un état « envoi en cours » sur le bouton et on empêche les
 * double-soumissions. L'état se réinitialise tout seul au rechargement.
 */
export default class extends Controller {
    submit(event) {
        if (this.submitting) {
            event.preventDefault();
            return;
        }
        this.submitting = true;
        this.element.classList.add('is-submitting');
        this.element.setAttribute('aria-busy', 'true');
    }
}
