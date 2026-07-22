// Librairie Google Analytics 4, activée uniquement si un identifiant est présent
// dans la meta ga-measurement-id ET que l'utilisateur a consenti à la mesure d'audience.
import { getConsent, CONSENT_EVENT } from '../consent/consent.js';

let gaLoaded = false;
let currentId = null;

function measurementId() {
    const meta = document.querySelector('meta[name="ga-measurement-id"]');
    const id = meta ? (meta.getAttribute('content') || '').trim() : '';
    return id ? id : null;
}

function loadGa(id) {
    if (gaLoaded) return;
    gaLoaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    // send_page_view:false — les pages vues sont émises manuellement (site en SPA Turbo)
    window.gtag('config', id, { anonymize_ip: true, send_page_view: false });
    const s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(s);
}

function disableGa(id) {
    window['ga-disable-' + id] = true;
    document.cookie.split(';').forEach((c) => {
        const name = c.split('=')[0].trim();
        if (name.indexOf('_ga') === 0) {
            document.cookie = name + '=; Max-Age=0; path=/';
        }
    });
}

export function track(name, params = {}) {
    if (!gaLoaded || typeof window.gtag !== 'function') return;
    window.gtag('event', name, params);
}

export function pageview() {
    if (!gaLoaded || typeof window.gtag !== 'function') return;
    window.gtag('event', 'page_view', {
        page_path: window.location.pathname + window.location.search,
        page_location: window.location.href,
        page_title: document.title,
    });
}

function onConsentChanged(consent) {
    if (!currentId) return;
    if (consent && consent.analytics) {
        if (gaLoaded) {
            window['ga-disable-' + currentId] = false; // ré-autorise après un retrait
        } else {
            loadGa(currentId);
            pageview(); // activation par interaction : compter la page courante
        }
    } else if (gaLoaded) {
        disableGa(currentId);
    }
}

function onClickDelegate(e) {
    const el = e.target.closest('[data-ga-event]');
    if (!el) return;
    const name = el.getAttribute('data-ga-event');
    if (!name) return;
    const params = {};
    for (const attr of el.attributes) {
        if (attr.name.startsWith('data-ga-') && attr.name !== 'data-ga-event') {
            params[attr.name.slice('data-ga-'.length)] = attr.value;
        }
    }
    track(name, params);
}

export function initAnalytics() {
    currentId = measurementId();
    if (!currentId) return; // no-op total : aucun identifiant configuré

    document.addEventListener('click', onClickDelegate, true);
    document.addEventListener('turbo:load', () => { if (gaLoaded) pageview(); });
    document.addEventListener(CONSENT_EVENT, (e) => onConsentChanged(e.detail));

    // Activation au chargement si consentement pré-existant : pas de pageview ici,
    // le turbo:load courant s'en charge (évite le doublon).
    const existing = getConsent();
    if (existing && existing.analytics) loadGa(currentId);

    window.giuliaAnalytics = { track, pageview };
}
