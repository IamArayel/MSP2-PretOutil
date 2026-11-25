<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Barre de recherche (ticket #109) : l'extension unaccent fait trouver « Échelle » en tapant
 * « echelle ». Extension de confiance (PostgreSQL 13+) : le propriétaire de la base l'active,
 * et pretoutil_app peut appeler unaccent() sans droit supplémentaire.
 */
final class Version20261125150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extension unaccent (recherche par nom sans tenir compte des accents)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS unaccent');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP EXTENSION IF EXISTS unaccent');
    }
}
