<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index partiel pour le filtre de disponibilité du catalogue (ticket #33) : sans lui,
 * PostgreSQL lit toute la table reservation (voir docs/conception/requetes-catalogue.md).
 */
final class Version20261124044037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index partiel idx_reservation_dates_actives (catalogue)';
    }

    public function up(Schema $schema): void
    {
        // PostgreSQL réécrit la condition ; la forme réécrite figure dans le mapping de Reservation.
        $this->addSql("CREATE INDEX idx_reservation_dates_actives ON reservation (date_debut, date_fin) WHERE statut IN ('confirmee', 'en_cours')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_reservation_dates_actives');
    }
}
