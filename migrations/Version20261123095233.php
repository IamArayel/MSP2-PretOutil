<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Contraintes que Doctrine ne sait pas générer, recopiées de docs/conception/mpd.sql (ticket #18).
 *
 * - CHECK : valeurs contrôlées, dates, cycle de vie de la réservation ;
 * - EXCLUDE : deux réservations actives d'un même objet ne se chevauchent pas (RG2).
 *
 * Doctrine ne lit pas les contraintes CHECK et ignore l'index de la contrainte EXCLUDE
 * (voir App\Doctrine\IndexChevauchementListener) : aucune migration future ne les supprimera.
 */
final class Version20261123095233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contraintes CHECK et EXCLUDE (RG1, RG2, cycle de vie)';
    }

    public function up(Schema $schema): void
    {
        // Combine l'égalité sur objet_id (btree) et le chevauchement de périodes (gist) dans une même contrainte.
        $this->addSql('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // L'application normalise l'email en minuscules : la base refuse toute variante.
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT ck_utilisateur_email_minuscules CHECK (email = LOWER(email))');

        $this->addSql("ALTER TABLE objet ADD CONSTRAINT ck_objet_etat CHECK (etat IN ('bon', 'abime', 'hors_service'))");

        $this->addSql("ALTER TABLE reservation ADD CONSTRAINT ck_reservation_statut CHECK (statut IN ('confirmee', 'en_cours', 'terminee', 'annulee'))");
        $this->addSql("ALTER TABLE reservation ADD CONSTRAINT ck_reservation_etat_retour CHECK (etat_retour IS NULL OR etat_retour IN ('bon', 'abime', 'hors_service'))");
        // RG1 (partie vérifiable en base ; « pas dans le passé » dépend de la date du jour, contrôlé par le service).
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT ck_reservation_dates CHECK (date_fin >= date_debut)');
        // Une sortie a toujours une date et un gestionnaire, ou ni l'un ni l'autre.
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT ck_reservation_sortie CHECK ((sortie_le IS NULL) = (sortie_par_id IS NULL))');
        // Un retour a une date, un gestionnaire et un état, ou rien.
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT ck_reservation_retour CHECK ((retour_le IS NULL AND retour_par_id IS NULL AND etat_retour IS NULL) OR (retour_le IS NOT NULL AND retour_par_id IS NOT NULL AND etat_retour IS NOT NULL))');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT ck_reservation_retour_apres_sortie CHECK (retour_le IS NULL OR (sortie_le IS NOT NULL AND retour_le >= sortie_le))');
        // Cycle de vie : le statut est cohérent avec les dates de sortie et de retour.
        $this->addSql("ALTER TABLE reservation ADD CONSTRAINT ck_reservation_cycle_de_vie CHECK ((statut IN ('confirmee', 'annulee') AND sortie_le IS NULL AND retour_le IS NULL) OR (statut = 'en_cours' AND sortie_le IS NOT NULL AND retour_le IS NULL) OR (statut = 'terminee' AND sortie_le IS NOT NULL AND retour_le IS NOT NULL))");

        // RG2 : bornes incluses '[]', un objet rendu le 20 ne peut pas être réservé à partir du 20.
        $this->addSql("ALTER TABLE reservation ADD CONSTRAINT ex_reservation_chevauchement EXCLUDE USING gist (objet_id WITH =, daterange(date_debut, date_fin, '[]') WITH &&) WHERE (statut IN ('confirmee', 'en_cours'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT ex_reservation_chevauchement');
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT ck_reservation_cycle_de_vie');
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT ck_reservation_retour_apres_sortie');
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT ck_reservation_retour');
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT ck_reservation_sortie');
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT ck_reservation_dates');
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT ck_reservation_etat_retour');
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT ck_reservation_statut');
        $this->addSql('ALTER TABLE objet DROP CONSTRAINT ck_objet_etat');
        $this->addSql('ALTER TABLE utilisateur DROP CONSTRAINT ck_utilisateur_email_minuscules');
        // L'extension btree_gist est conservée : d'autres objets de la base peuvent en dépendre.
    }
}
