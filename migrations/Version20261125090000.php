<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Photo des objets (ticket #94) : nom d'un fichier de assets/images/objets/, facultatif.
 * La contrainte n'accepte qu'un nom simple en .webp : aucun chemin ne peut être enregistré.
 */
final class Version20261125090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Colonne objet.image (photo de l\'objet)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objet ADD image VARCHAR(100) DEFAULT NULL');
        $this->addSql("ALTER TABLE objet ADD CONSTRAINT ck_objet_image CHECK (image ~ '^[a-z0-9-]+\\.webp$')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objet DROP CONSTRAINT ck_objet_image');
        $this->addSql('ALTER TABLE objet DROP image');
    }
}
