<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Déclare à Doctrine l'index qui porte la contrainte ex_reservation_chevauchement (RG2).
 *
 * Cette contrainte EXCLUDE est créée par une migration écrite à la main. PostgreSQL la
 * stocke avec un index que Doctrine lit comme un index ordinaire : sans cette déclaration,
 * chaque génération de migration proposerait de le supprimer, et avec lui la contrainte.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class IndexChevauchementListener
{
    public const string NOM_INDEX = 'ex_reservation_chevauchement';

    /** Condition telle que PostgreSQL la restitue, pour que Doctrine la juge identique. */
    private const string CONDITION = "((statut)::text = ANY ((ARRAY['confirmee'::character varying, 'en_cours'::character varying])::text[]))";

    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();

        if (!$schema->hasTable('reservation')) {
            return;
        }

        $table = $schema->getTable('reservation');

        if (!$table->hasIndex(self::NOM_INDEX)) {
            $table->addIndex(['objet_id'], self::NOM_INDEX, [], ['where' => self::CONDITION]);
        }
    }
}
