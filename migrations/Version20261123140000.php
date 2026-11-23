<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Droits minimaux du rôle applicatif pretoutil_app, recopiés de docs/conception/mpd.sql (ticket #22).
 *
 * Le rôle est créé, avec son mot de passe, par docker/postgres/initdb/01-role-applicatif.sh
 * à la création du volume. Si la migration tourne sur une base où il n'existe pas encore,
 * elle le crée sans droit de connexion : un administrateur lui donne ensuite un mot de passe.
 *
 * Les migrations et les fixtures s'exécutent avec le propriétaire du schéma (connexion
 * Doctrine « proprietaire ») : elles ne dépendent pas de ces droits.
 */
final class Version20261123140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Droits minimaux du rôle applicatif pretoutil_app';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'pretoutil_app') THEN
                    CREATE ROLE pretoutil_app NOLOGIN;
                END IF;
            END
            $$
            SQL);

        $this->addSql('GRANT USAGE ON SCHEMA public TO pretoutil_app');
        $this->addSql('GRANT SELECT, INSERT, UPDATE ON utilisateur, categorie, objet, reservation TO pretoutil_app');
        // Suppression physique autorisée uniquement pour un objet jamais réservé (US6) ;
        // la clé étrangère fk_reservation_objet (ON DELETE RESTRICT) bloque les autres cas.
        $this->addSql('GRANT DELETE ON objet TO pretoutil_app');
        $this->addSql('GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO pretoutil_app');
    }

    public function down(Schema $schema): void
    {
        // Le rôle n'est pas supprimé : il peut posséder des sessions ou servir à d'autres bases.
        $this->addSql('REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM pretoutil_app');
        $this->addSql('REVOKE ALL ON utilisateur, categorie, objet, reservation FROM pretoutil_app');
        $this->addSql('REVOKE ALL ON SCHEMA public FROM pretoutil_app');
    }
}
