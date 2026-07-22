// Source de vérité du consentement cookies. Logique pure : localStorage + CustomEvent.
const KEY = 'giulia_consent';
const VERSION = 1;
const MAX_AGE_MS = 1000 * 60 * 60 * 24 * 30 * 6; // ~6 mois

export const CONSENT_EVENT = 'giulia:consent-changed';

export function getConsent() {
    try {
        const raw = localStorage.getItem(KEY);
        if (!raw) return null;
        const data = JSON.parse(raw);
        if (data.v !== VERSION) return null;
        if (typeof data.ts !== 'number' || Date.now() - data.ts > MAX_AGE_MS) return null;
        return { analytics: !!data.analytics, marketing: !!data.marketing };
    } catch (e) {
        return null;
    }
}

export function setConsent({ analytics, marketing }) {
    const value = { analytics: !!analytics, marketing: !!marketing, ts: Date.now(), v: VERSION };
    try {
        localStorage.setItem(KEY, JSON.stringify(value));
    } catch (e) {
        // stockage indisponible : on continue sans persister
    }
    document.dispatchEvent(new CustomEvent(CONSENT_EVENT, {
        detail: { analytics: value.analytics, marketing: value.marketing },
    }));
}
