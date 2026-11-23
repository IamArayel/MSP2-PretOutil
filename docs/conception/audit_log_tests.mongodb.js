// Prêt'Outil : jeu d'essai du validateur de la collection audit_log (ticket #2)
// Prérequis : audit_log.mongodb.js exécuté sur une base de test.
// Exécution : mongosh "<uri>/pretoutil_test" docs/conception/audit_log_tests.mongodb.js
// Les documents insérés sont supprimés en fin de script.

const resultats = [];
const essai = (cas, doc, attendu) => {
  let obtenu;
  try {
    db.audit_log.insertOne(doc);
    obtenu = 'accepté';
  } catch (e) {
    // 121 = DocumentValidationFailure
    obtenu = `refusé (${e.code})`;
  }
  resultats.push({ cas, attendu, obtenu, bilan: obtenu.startsWith(attendu) ? 'OK' : 'ÉCART' });
};

const base = () => ({
  horodatage: new Date(),
  action: 'RETOUR_ENREGISTRE',
  acteur: { id: 2, email: 'lea.bernard@exemple.fr' },
  cible: { type: 'reservation', id: 128 },
  details: { objet: 'Réchaud à gaz', etat_retour: 'abime' },
  essai: true,
});
// Le champ « essai » n'est pas prévu par le schéma : on le retire des documents attendus valides.
const valide = (doc) => { delete doc.essai; return doc; };

essai('Retour enregistré valide', valide(base()), 'accepté');
essai('Connexion échouée, acteur anonyme', valide({
  ...base(),
  action: 'CONNEXION_ECHOUEE',
  acteur: { id: null, email: 'inconnu@exemple.fr' },
  cible: { type: 'utilisateur', id: null },
  details: { tentative: 5 },
}), 'accepté');
essai('Identifiant en entier 64 bits', valide({ ...base(), cible: { type: 'reservation', id: Long(128) } }), 'accepté');
essai('Action inconnue', valide({ ...base(), action: 'OBJET_VOLE' }), 'refusé');
essai('Horodatage en texte', valide({ ...base(), horodatage: '2026-12-07' }), 'refusé');
essai('Acteur sans email', valide({ ...base(), acteur: { id: 2 } }), 'refusé');
essai('Champ non prévu', base(), 'refusé');
essai('Type de cible inconnu', valide({ ...base(), cible: { type: 'categorie', id: 1 } }), 'refusé');

console.table(resultats);
print(`OK : ${resultats.filter((r) => r.bilan === 'OK').length} / ${resultats.length}`);

db.audit_log.deleteMany({ 'acteur.email': { $in: ['lea.bernard@exemple.fr', 'inconnu@exemple.fr'] } });
