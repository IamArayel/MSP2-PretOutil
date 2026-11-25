<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Categorie;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Filtres du catalogue (US3), tous facultatifs et combinables.
 *
 * Rempli par le formulaire de l'écran E3 et validé avant d'être passé au repository.
 * La période ne s'applique que si les deux dates sont fournies.
 */
final class CriteresCatalogue
{
    public const int LONGUEUR_MAX_RECHERCHE = 100;

    public function __construct(
        /** Partie du nom de l'objet, sans tenir compte de la casse ni des accents (#109). */
        public ?string $recherche = null,
        public ?Categorie $categorie = null,
        #[Assert\GreaterThanOrEqual('today', message: 'La date de début ne peut pas être dans le passé.')]
        public ?\DateTimeImmutable $debut = null,
        #[Assert\GreaterThanOrEqual(propertyPath: 'debut', message: 'La date de fin doit être après la date de début.')]
        public ?\DateTimeImmutable $fin = null,
    ) {
    }

    public function aUnePeriode(): bool
    {
        return null !== $this->debut && null !== $this->fin;
    }

    /** Une période se saisit avec ses deux dates : une date seule est signalée plutôt qu'ignorée. */
    #[Assert\Callback]
    public function validerPeriodeComplete(ExecutionContextInterface $contexte): void
    {
        if (null !== $this->debut && null === $this->fin) {
            $contexte->buildViolation('Indiquez aussi la date de fin.')->atPath('fin')->addViolation();
        }
        if (null === $this->debut && null !== $this->fin) {
            $contexte->buildViolation('Indiquez aussi la date de début.')->atPath('debut')->addViolation();
        }
    }
}
