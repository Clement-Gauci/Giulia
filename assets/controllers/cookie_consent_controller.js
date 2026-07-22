import { Controller } from '@hotwired/stimulus';
import { getConsent, setConsent } from '../consent/consent.js';

// Durées de la pastille de confirmation après un choix (alignées sur le comp).
const CHIP_VISIBLE_MS = 5000;
const CHIP_OUT_MS = 550;

export default class extends Controller {
    static targets = ['banner', 'chip', 'prefs', 'prefsButton', 'saveButton', 'analyticsToggle', 'marketingToggle'];

    connect() {
        const consent = getConsent();
        this.analytics = consent ? consent.analytics : false;
        this.marketing = consent ? consent.marketing : false;
        if (consent) {
            // Choix déjà enregistré : rien n'est affiché (réouverture via la page mentions légales).
            this.bannerTarget.hidden = true;
            this.chipTarget.hidden = true;
        } else {
            this._openBanner();
        }
        this._syncToggles();
    }

    disconnect() { this._clearTimers(); }

    openPrefs() {
        this.prefsTarget.hidden = false;
        this.prefsButtonTarget.hidden = true;
        this.saveButtonTarget.hidden = false;
    }

    toggleAnalytics() { this.analytics = !this.analytics; this._syncToggles(); }
    toggleMarketing() { this.marketing = !this.marketing; this._syncToggles(); }

    acceptAll() {
        this.analytics = true; this.marketing = true;
        setConsent({ analytics: true, marketing: true });
        this._trackChoice('all');
        this._dismiss();
    }

    refuseAll() {
        this.analytics = false; this.marketing = false;
        setConsent({ analytics: false, marketing: false });
        this._dismiss();
    }

    saveChoices() {
        setConsent({ analytics: this.analytics, marketing: this.marketing });
        this._trackChoice('custom');
        this._dismiss();
    }

    reopen() {
        this._clearTimers();
        this.chipTarget.classList.remove('cookie-chip--in', 'cookie-chip--out');
        this._openBanner();
        this._syncToggles();
    }

    // Bandeau ouvert, préférences repliées, pastille masquée.
    _openBanner() {
        this.bannerTarget.hidden = false;
        this.chipTarget.hidden = true;
        this.prefsTarget.hidden = true;
        this.prefsButtonTarget.hidden = false;
        this.saveButtonTarget.hidden = true;
    }

    // Après un choix : le bandeau se ferme, la pastille apparaît puis s'efface après quelques secondes.
    _dismiss() {
        this._clearTimers();
        this.bannerTarget.hidden = true;
        this.prefsTarget.hidden = true;
        this.prefsButtonTarget.hidden = false;
        this.saveButtonTarget.hidden = true;

        this.chipTarget.hidden = false;
        this.chipTarget.classList.remove('cookie-chip--out');
        this.chipTarget.classList.add('cookie-chip--in');

        this._hideTimer = setTimeout(() => {
            this.chipTarget.classList.remove('cookie-chip--in');
            this.chipTarget.classList.add('cookie-chip--out');
            this._outTimer = setTimeout(() => {
                this.chipTarget.hidden = true;
                this.chipTarget.classList.remove('cookie-chip--out');
            }, CHIP_OUT_MS);
        }, CHIP_VISIBLE_MS);
    }

    _clearTimers() {
        clearTimeout(this._hideTimer);
        clearTimeout(this._outTimer);
    }

    _syncToggles() {
        this._sync(this.analyticsToggleTarget, this.analytics);
        this._sync(this.marketingToggleTarget, this.marketing);
    }

    _sync(el, on) {
        el.classList.toggle('is-on', on);
        el.setAttribute('aria-checked', String(on));
    }

    _trackChoice(kind) {
        if (window.giuliaAnalytics && typeof window.giuliaAnalytics.track === 'function') {
            window.giuliaAnalytics.track('cookie_consent', { choice: kind });
        }
    }
}
