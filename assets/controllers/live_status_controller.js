import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { url: String, refresh: { type: Number, default: 60000 } };
    static targets = ['label', 'detail'];

    connect() {
        this.refresh();                                                  // corrige tout de suite un état figé (page en cache, onglet rouvert)
        this.timer = setInterval(() => this.refresh(), this.refreshValue);
        this.onVisible = () => { if (!document.hidden) this.refresh(); }; // corrige au retour sur l'onglet
        document.addEventListener('visibilitychange', this.onVisible);
    }

    disconnect() {
        clearInterval(this.timer);
        document.removeEventListener('visibilitychange', this.onVisible);
    }

    async refresh() {
        try {
            const res = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            if (this.hasLabelTarget) this.labelTarget.textContent = data.label;
            if (this.hasDetailTarget) this.detailTarget.textContent = data.detail;
            if (this.element.classList.contains('badge')) {
                this.element.classList.toggle('badge--open', data.open);
                this.element.classList.toggle('badge--closed', !data.open);
            }
        } catch (e) { /* silencieux : on garde l'état serveur */ }
    }
}
