/*
 * Recherche à la volée des écrans E3 et E7 (#109) : dès 3 lettres, la page est redemandée
 * au serveur avec le formulaire, et seules les zones [data-recherche-zone] sont remplacées.
 *
 * Amélioration progressive : sans JavaScript, ou si la requête échoue, le bouton Rechercher
 * envoie le formulaire comme avant. Le nombre de résultats est annoncé aux lecteurs d'écran.
 */
const LETTRES_MIN = 3;
const DELAI_MS = 300;

function initialiser(bloc) {
    const formulaire = bloc.closest('form');
    const champ = bloc.querySelector('[data-recherche-champ]');
    const statut = bloc.querySelector('[data-recherche-statut]');

    if (!formulaire || !champ || !statut) {
        return;
    }

    let derniereRecherche = champ.value.trim();
    let minuterie = null;
    let requeteEnCours = null;

    // Paramètres du formulaire sans les champs vides ni le numéro de page : on repart de la page 1.
    const adresse = () => {
        const url = new URL(formulaire.action);
        new FormData(formulaire).forEach((valeur, nom) => {
            if (nom !== 'page' && valeur !== '') {
                url.searchParams.append(nom, valeur);
            }
        });
        return url;
    };

    const rechercher = async () => {
        // Une saisie plus récente remplace la précédente.
        requeteEnCours?.abort();
        requeteEnCours = new AbortController();
        const url = adresse();

        try {
            const reponse = await fetch(url, { headers: { Accept: 'text/html' }, signal: requeteEnCours.signal });
            if (!reponse.ok) {
                return;
            }
            const page = new DOMParser().parseFromString(await reponse.text(), 'text/html');
            // Lu avant le remplacement, qui déplace les zones de la page reçue vers le document.
            const annonce = page.querySelector('[data-recherche-annonce]')?.textContent.replace(/\s+/g, ' ').trim() ?? '';

            document.querySelectorAll('[data-recherche-zone]').forEach((zone) => {
                const nouvelle = page.getElementById(zone.id);
                if (nouvelle) {
                    zone.replaceWith(nouvelle);
                }
            });
            history.replaceState(null, '', url);
            statut.textContent = annonce;
        } catch {
            // Requête annulée ou réseau coupé : la page reste telle quelle, le bouton fonctionne.
        }
    };

    champ.addEventListener('input', () => {
        const texte = champ.value.trim();
        clearTimeout(minuterie);
        // Moins de 3 lettres : on attend, sauf si le champ est vidé (retour à la liste complète).
        if (texte === derniereRecherche || (texte.length < LETTRES_MIN && texte !== '')) {
            return;
        }
        minuterie = setTimeout(() => {
            derniereRecherche = texte;
            rechercher();
        }, DELAI_MS);
    });
}

document.querySelectorAll('[data-recherche]').forEach(initialiser);
