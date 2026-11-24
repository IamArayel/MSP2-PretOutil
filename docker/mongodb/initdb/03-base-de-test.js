// Prêt'Outil : base MongoDB des tests d'intégration (pretoutil_test).
// Même collection audit_log (validateur, index, rôle auditAppendOnly) que la base de
// l'application, créée par le même script ; l'utilisateur pretoutil_app reçoit le rôle
// sur cette base aussi : les tests tournent avec les mêmes droits que l'application.
//
// Exécuté à la création du volume, après 01-audit_log.js et 02-utilisateur-app.js.
// Sur un volume existant (script rejouable), en root :
//   docker compose exec mongodb sh -c 'mongosh --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin "$MONGO_INITDB_DATABASE" /docker-entrypoint-initdb.d/03-base-de-test.js'

const baseApplication = db.getName();
const baseDeTest = `${baseApplication}_test`;

db = db.getSiblingDB(baseDeTest);
load('/docker-entrypoint-initdb.d/01-audit_log.js');

db.getSiblingDB(baseApplication).grantRolesToUser('pretoutil_app', [
  { role: 'auditAppendOnly', db: baseDeTest },
]);

print(`Base ${baseDeTest} prête : audit_log et rôle auditAppendOnly accordé à pretoutil_app.`);
