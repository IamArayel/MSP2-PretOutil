<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Objet;
use App\Enum\StatutDuJour;

/**
 * Réponse de l'API des disponibilités (US11). Ne contient que des dates et des statuts :
 * jamais l'adhérent qui a réservé (minimisation RGPD).
 */
final readonly class DisponibiliteObjet implements \JsonSerializable
{
    /**
     * @param list<array{dateDebut: \DateTimeImmutable, dateFin: \DateTimeImmutable}> $periodesReservees
     */
    public function __construct(
        private Objet $objet,
        private StatutDuJour $statutDuJour,
        private array $periodesReservees,
        private ?PeriodeDemandee $periodeDemandee = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $reponse = [
            'objet' => ['id' => $this->objet->getId(), 'nom' => $this->objet->getNom()],
            'statut_du_jour' => ['code' => $this->statutDuJour->value, 'libelle' => $this->statutDuJour->libelle()],
            'periodes_reservees' => array_map(
                static fn (array $periode): array => [
                    'debut' => $periode['dateDebut']->format('Y-m-d'),
                    'fin' => $periode['dateFin']->format('Y-m-d'),
                ],
                $this->periodesReservees,
            ),
        ];

        if (null !== $this->periodeDemandee) {
            $reponse['periode_demandee'] = $this->periodeDemandee;
        }

        return $reponse;
    }
}
