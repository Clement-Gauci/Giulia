import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.min.css';

// Carte interactive du bloc « Nous trouver » (page contact).
// Tuiles CartoDB Voyager (clair) — pas de clé ni de cookie. Marqueur = pin SVG maison
// via divIcon, ce qui évite les images PNG de Leaflet (chemins cassés avec AssetMapper).
export default class extends Controller {
    static values = {
        lat: Number,
        lng: Number,
        zoom: { type: Number, default: 16 },
        directionsUrl: String,
    };

    connect() {
        this.map = L.map(this.element, {
            center: [this.latValue, this.lngValue],
            zoom: this.zoomValue,
            scrollWheelZoom: false,   // on ne piège pas le scroll de la page
            attributionControl: true,
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            maxZoom: 20,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> ' +
                'contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        }).addTo(this.map);

        const icon = L.divIcon({
            className: 'map__marker',
            html:
                '<svg width="34" height="34" viewBox="0 0 24 24" fill="#b3743f" stroke="#fffdf8" stroke-width="1.4" stroke-linejoin="round">' +
                '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>' +
                '<circle cx="12" cy="10" r="3" fill="#fffdf8" stroke="none"></circle></svg>',
            iconSize: [34, 34],
            iconAnchor: [17, 33],   // pointe du pin en bas-centre
        });

        const marker = L.marker([this.latValue, this.lngValue], {
            icon,
            keyboard: true,
            title: 'Ouvrir l’itinéraire dans Maps',
            alt: 'Giulia — voir l’itinéraire',
        }).addTo(this.map);

        if (this.directionsUrlValue) {
            marker.on('click', () => window.open(this.directionsUrlValue, '_blank', 'noopener'));
        }
    }

    disconnect() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }
}
