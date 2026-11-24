<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index partiel des réservations en cours (ticket #44) : retours en retard exclus du
 * catalogue et refusés par ReservationService (voir docs/conception/requetes-catalogue.md).
 */
final class Version20261124054944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index partiel idx_reservation_en_cours (retours en retard)';
    }

    public function up(Schema $schema): void
    {
        // PostgreSQL réécrit la condition ; la forme réécrite figure dans le mapping de Reservation.
        $this->addSql("CREATE INDEX idx_reservation_en_cours ON reservation (date_fin) WHERE statut = 'en_cours'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_reservation_en_cours');
    }
}
