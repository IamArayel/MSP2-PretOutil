/*
 * Demande de confirmation avant une action irréversible (annuler une réservation,
 * supprimer un objet) : formulaires marqués data-confirmation="Question ?".
 *
 * Remplace les attributs onsubmit="..." : la politique de sécurité du contenu (CSP)
 * n'exécute aucun JavaScript écrit dans le HTML, seulement les scripts de l'application.
 */
document.addEventListener('submit', (evenement) => {
    const formulaire = evenement.target;
    const question = formulaire instanceof HTMLFormElement ? formulaire.dataset.confirmation : undefined;

    if (question && !window.confirm(question)) {
        evenement.preventDefault();
    }
});
