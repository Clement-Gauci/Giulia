import { Controller } from '@hotwired/stimulus';
import { getConsent, setConsent } from '../consent/consent.js';

export default class extends Controller {
    static targets = ['banner', 'chip', 'prefs', 'prefsButton', 'saveButton', 'analyticsToggle', 'marketingToggle'];

    connect() {
        this.analytics = false;
        this.marketing = false;
        const consent = getConsent();
        if (consent) {
            this.analytics = consent.analytics;
            this.marketing = consent.marketing;
            this._showChipOnly();
        } else {
            this._showBanner();
        }
        this._syncToggles();
    }

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
        this._showChipOnly();
    }

    refuseAll() {
        this.analytics = false; this.marketing = false;
        setConsent({ analytics: false, marketing: false });
        this._showChipOnly();
    }

    saveChoices() {
        setConsent({ analytics: this.analytics, marketing: this.marketing });
        this._trackChoice('custom');
        this._showChipOnly();
    }

    reopen() { this._showBanner(); }

    _showBanner() {
        this.bannerTarget.hidden = false;
        this.chipTarget.hidden = true;
    }

    _showChipOnly() {
        this.bannerTarget.hidden = true;
        this.chipTarget.hidden = false;
        this.prefsTarget.hidden = true;
        this.prefsButtonTarget.hidden = false;
        this.saveButtonTarget.hidden = true;
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
