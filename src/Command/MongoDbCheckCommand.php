<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Driver\Exception\ServerException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Vérifie la connexion à MongoDB avec le compte de l'application (pretoutil_app,
 * rôle auditAppendOnly) et que ses droits sont bien limités :
 * lecture de audit_log autorisée, écriture hors de audit_log refusée.
 * Aucune donnée n'est écrite dans le journal.
 */
#[AsCommand(
    name: 'app:mongodb:check',
    description: 'Teste la connexion à MongoDB et les droits du compte applicatif',
)]
final class MongoDbCheckCommand
{
    /** Code MongoDB « Unauthorized » : l'utilisateur n'a pas le droit demandé. */
    private const CODE_NON_AUTORISE = 13;

    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $base = $this->documentManager->getConfiguration()->getDefaultDB();
        $client = $this->documentManager->getClient();

        $nombre = $client->getCollection($base, 'audit_log')->countDocuments();
        $io->writeln(sprintf('Lecture de "%s.audit_log" : %d document(s).', $base, $nombre));

        try {
            $client->getCollection($base, 'connexion_test')->insertOne(['essai' => true]);
        } catch (ServerException $e) {
            if (self::CODE_NON_AUTORISE === $e->getCode()) {
                $io->writeln(sprintf('Écriture dans "%s.connexion_test" : refusée, comme attendu.', $base));
                $io->success('MongoDB répond et le compte applicatif a des droits limités au journal d\'audit.');

                return Command::SUCCESS;
            }

            throw $e;
        }

        $client->getCollection($base, 'connexion_test')->deleteMany(['essai' => true]);
        $io->error('Le compte applicatif a pu écrire hors de audit_log : il a trop de droits.');

        return Command::FAILURE;
    }
}
