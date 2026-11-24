// Prêt'Outil : collection MongoDB du journal d'audit (RG8, US10)
// Ticket #2. Exécution : mongosh "<uri>/pretoutil" docs/conception/audit_log.mongodb.js
// Script rejouable : sur une base existante, il met à jour le validateur (collMod)
// sans toucher aux documents ; les index et le rôle ne sont créés qu'une fois.
// Un document par action sensible. Le journal est en ajout seul :
// l'application n'a ni droit de modification ni droit de suppression.

const ACTIONS = [
  'COMPTE_CREE',
  'CONNEXION_ECHOUEE',
  'COMPTE_DESACTIVE',
  'ROLE_MODIFIE',
  'OBJET_CREE',
  'OBJET_MODIFIE',
  'OBJET_DESACTIVE',
  'OBJET_SUPPRIME', // ajoutée à J6 (#56) : suppression physique d'un objet jamais réservé
  'RESERVATION_CREEE',
  'RESERVATION_ANNULEE',
  'SORTIE_VALIDEE',
  'RETOUR_ENREGISTRE',
];

const CIBLES = ['utilisateur', 'objet', 'reservation'];

const validation = {
  validationLevel: 'strict',
  validationAction: 'error',
  validator: {
    $jsonSchema: {
      bsonType: 'object',
      required: ['horodatage', 'action', 'acteur', 'cible', 'details'],
      additionalProperties: false,
      properties: {
        _id: { bsonType: 'objectId' },
        horodatage: { bsonType: 'date', description: 'Date et heure UTC de l\'action' },
        action: { enum: ACTIONS, description: 'Type d\'action journalisée' },
        acteur: {
          bsonType: 'object',
          required: ['id', 'email'],
          additionalProperties: false,
          properties: {
            // null pour une action anonyme (ex. connexion échouée sur un email inconnu)
            id: { bsonType: ['int', 'long', 'null'] },
            // Recopié et non référencé : le journal reste lisible même si le compte change
            email: { bsonType: 'string', maxLength: 180 },
          },
        },
        cible: {
          bsonType: 'object',
          required: ['type', 'id'],
          additionalProperties: false,
          properties: {
            type: { enum: CIBLES },
            id: { bsonType: ['int', 'long', 'null'] },
          },
        },
        // Forme libre selon l'action : c'est la raison du choix de MongoDB
        details: { bsonType: 'object' },
      },
    },
  },
};

if (db.getCollectionNames().includes('audit_log')) {
  db.runCommand({ collMod: 'audit_log', ...validation });
} else {
  db.createCollection('audit_log', validation);
}

// Liste paginée du plus récent au plus ancien (US10) ; index TTL :
// MongoDB supprime automatiquement les entrées de plus d'un an (RGPD).
db.audit_log.createIndex(
  { horodatage: 1 },
  { name: 'ttl_horodatage_1an', expireAfterSeconds: 60 * 60 * 24 * 365 },
);

// Filtre par type d'action, trié par date (US10).
db.audit_log.createIndex(
  { action: 1, horodatage: -1 },
  { name: 'idx_action_horodatage' },
);

// Rôle applicatif : lecture et insertion seulement (journal en ajout seul).
// L'utilisateur est créé hors du dépôt, avec un mot de passe issu de l'environnement :
//   db.createUser({ user: 'pretoutil_app', pwd: '...', roles: ['auditAppendOnly'] })
if (!db.getRole('auditAppendOnly')) {
  db.createRole({
    role: 'auditAppendOnly',
    privileges: [
      { resource: { db: db.getName(), collection: 'audit_log' }, actions: ['find', 'insert'] },
    ],
    roles: [],
  });
}
