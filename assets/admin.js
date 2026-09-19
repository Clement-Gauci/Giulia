// Point d'entrée du dashboard. Volontairement distinct de `app.js` : le
// back-office ne charge ni le CSS du site public, ni les mesures d'audience —
// on ne suit pas l'activité de l'équipe.
import './stimulus_bootstrap.js';
import './styles/admin.css';
