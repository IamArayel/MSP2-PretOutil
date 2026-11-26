<?php

declare(strict_types=1);

namespace App\Command;

use App\Demo\ChargeurJeuDEssai;
use App\Demo\JeuDEssai;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Données de démonstration en production (ticket #115) : le jeu d'essai des fixtures, avec un
 * mot de passe secret lu dans DEMO_MOT_DE_PASSE (variable de Dokploy, jamais dans le dépôt).
 *
 * Les fixtures ne font pas partie de l'image de production (dépendance de développement) et
 * vident la base : cette commande, elle, refuse d'écrire si un compte existe déjà, pour ne
 * jamais mélanger la démonstration à de vraies données.
 */
#[AsCommand(
    name: 'app:demo:initialiser',
    description: 'Charge les données de démonstration dans une base vide (mot de passe : DEMO_MOT_DE_PASSE)',
)]
final class DemoInitialiserCommand
{
    /** Même longueur minimale que l'inscription (US1). */
    private const int LONGUEUR_MINIMALE = 12;

    public function __construct(
        private readonly ChargeurJeuDEssai $chargeur,
        private readonly EntityManagerInterface $entityManager,
        private readonly UtilisateurRepository $utilisateurRepository,
        #[Autowire(env: 'default::DEMO_MOT_DE_PASSE')]
        private readonly ?string $motDePasse,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $motDePasse = (string) $this->motDePasse;

        if (mb_strlen($motDePasse) < self::LONGUEUR_MINIMALE) {
            $io->error(\sprintf('DEMO_MOT_DE_PASSE doit être défini et contenir au moins %d caractères.', self::LONGUEUR_MINIMALE));

            return Command::FAILURE;
        }
        if (JeuDEssai::MOT_DE_PASSE_TEST === $motDePasse) {
            $io->error('DEMO_MOT_DE_PASSE ne peut pas être le mot de passe de développement : il est public dans le dépôt.');

            return Command::FAILURE;
        }

        $comptes = $this->utilisateurRepository->count([]);
        if ($comptes > 0) {
            $io->error(\sprintf('La base contient déjà %d compte(s) : rien n\'est écrit.', $comptes));

            return Command::FAILURE;
        }

        $nombres = $this->chargeur->charger($this->entityManager, $motDePasse);

        $io->success(\sprintf(
            'Démonstration chargée : %d catégories, %d comptes, %d objets (%d photos), %d réservations.',
            $nombres['categories'],
            $nombres['comptes'],
            $nombres['objets'],
            $nombres['photos'],
            $nombres['reservations'],
        ));
        $io->listing(array_map(
            static fn (array $compte): string => \sprintf('%s (%s)', $compte[0], $compte[4] ? 'actif' : 'désactivé'),
            array_values(JeuDEssai::COMPTES),
        ));
        $io->writeln('Mot de passe de ces comptes : celui de DEMO_MOT_DE_PASSE.');

        return Command::SUCCESS;
    }
}
