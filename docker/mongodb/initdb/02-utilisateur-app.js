// Prêt'Outil : crée l'utilisateur MongoDB de l'application à la création du volume.
// Exécuté après 01-audit_log.js (docs/conception/audit_log.mongodb.js), qui crée la
// collection audit_log et le rôle auditAppendOnly (find et insert sur audit_log seulement).
// Le mot de passe vient de .env.local (MONGODB_APP_PASSWORD), jamais du dépôt.

const motDePasse = process.env.MONGODB_APP_PASSWORD;
if (!motDePasse) {
  throw new Error('MONGODB_APP_PASSWORD doit être défini dans .env.local');
}

db.createUser({
  user: 'pretoutil_app',
  pwd: motDePasse,
  roles: [{ role: 'auditAppendOnly', db: db.getName() }],
});
