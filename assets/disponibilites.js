/*
 * Écran E4 : affiche la disponibilité de l'objet dès que les deux dates sont saisies (US11),
 * en appelant l'API REST des disponibilités (docs/conception/api.md).
 *
 * Amélioration progressive : sans JavaScript, ou si l'API ne répond pas, le formulaire
 * fonctionne comme avant et ReservationService vérifie tout à l'envoi.
 * Un appel par saisie de dates, jamais à intervalle régulier (pas de polling).
 */
// Composant message de la charte (assets/styles/app.css), comme les messages des gabarits.
const CLASSES = {
    disponible: ['message-succes'],
    indisponible: ['message-erreur'],
};

function initialiser(formulaire) {
    const url = formulaire.dataset.disponibilitesUrl;
    const debut = formulaire.querySelector('[name="reservation[debut]"]');
    const fin = formulaire.querySelector('[name="reservation[fin]"]');
    const resultat = formulaire.querySelector('[data-disponibilites-resultat]');

    if (!url || !debut || !fin || !resultat) {
        return;
    }

    let requeteEnCours = null;

    // La zone aria-live reste dans la page ; on remplace seulement le message qu'elle contient.
    const afficher = (texte, etat) => {
        resultat.replaceChildren();
        if (texte === '') {
            return;
        }
        const message = document.createElement('p');
        message.className = 'message mb-4';
        message.classList.add(...CLASSES[etat]);
        message.textContent = texte;
        resultat.append(message);
    };

    const verifier = async () => {
        if (!debut.value || !fin.value) {
            afficher('');
            return;
        }

        // Une saisie plus récente remplace la précédente.
        requeteEnCours?.abort();
        requeteEnCours = new AbortController();

        try {
            const parametres = new URLSearchParams({ debut: debut.value, fin: fin.value });
            const reponse = await fetch(`${url}?${parametres}`, {
                headers: { Accept: 'application/json' },
                signal: requeteEnCours.signal,
            });
            if (!reponse.ok) {
                afficher('');
                return;
            }

            const { periode_demandee: periode } = await reponse.json();
            if (periode.disponible) {
                afficher('Disponible sur ces dates.', 'disponible');
            } else {
                afficher(periode.motif, 'indisponible');
            }
        } catch (erreur) {
            if (erreur.name !== 'AbortError') {
                afficher('');
            }
        }
    };

    debut.addEventListener('change', verifier);
    fin.addEventListener('change', verifier);
}

document.querySelectorAll('form[data-disponibilites-url]').forEach(initialiser);
