/*
 * Bandeau d'information sur les cookies (#106), gabarit _partials/bandeau_cookies.html.twig.
 * Affiché tant que le visiteur n'a pas choisi « Compris » ; ce choix est gardé dans le
 * navigateur (localStorage), sans cookie. Si le stockage est indisponible (navigation privée
 * stricte), le bandeau se ferme pour la page en cours seulement.
 */
const CLE = 'pretoutil.bandeau-cookies';

function dejaCompris() {
    try {
        return window.localStorage.getItem(CLE) === 'compris';
    } catch {
        return false;
    }
}

function memoriserCompris() {
    try {
        window.localStorage.setItem(CLE, 'compris');
    } catch {
        // Stockage refusé : le bandeau reviendra à la page suivante.
    }
}

const bandeau = document.getElementById('bandeau-cookies');

if (bandeau && !dejaCompris()) {
    bandeau.hidden = false;
    bandeau.querySelector('[data-fermer-bandeau]')?.addEventListener('click', () => {
        memoriserCompris();
        bandeau.hidden = true;
    });
}
