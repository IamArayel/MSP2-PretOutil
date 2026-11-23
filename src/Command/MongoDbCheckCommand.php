<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Vérifie la connexion à MongoDB via Doctrine ODM :
 * écrit un document, le relit, puis le supprime.
 */
#[AsCommand(
    name: 'app:mongodb:check',
    description: 'Teste l\'écriture puis la lecture d\'un document MongoDB',
)]
final class MongoDbCheckCommand
{
    private const COLLECTION = 'connexion_test';

    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $database = $this->documentManager->getConfiguration()->getDefaultDB();
        $collection = $this->documentManager->getClient()->getCollection($database, self::COLLECTION);

        $jeton = bin2hex(random_bytes(8));
        $resultat = $collection->insertOne([
            'jeton' => $jeton,
            'horodatage' => new UTCDateTime(),
        ]);

        $document = $collection->findOne(['_id' => $resultat->getInsertedId()]);
        $collection->deleteOne(['_id' => $resultat->getInsertedId()]);

        if (null === $document || $document['jeton'] !== $jeton) {
            $io->error(sprintf('Le document écrit dans "%s.%s" n\'a pas pu être relu.', $database, self::COLLECTION));

            return Command::FAILURE;
        }

        $io->success(sprintf('MongoDB répond : document écrit puis relu dans "%s.%s".', $database, self::COLLECTION));

        return Command::SUCCESS;
    }
}
