<?php

declare(strict_types=1);

namespace App\Api;

/**
 * Disponibilité d'un objet sur la période demandée ; le motif est le message de la
 * règle de gestion qui refuserait la réservation.
 */
final readonly class PeriodeDemandee implements \JsonSerializable
{
    public function __construct(
        private \DateTimeImmutable $debut,
        private \DateTimeImmutable $fin,
        private ?string $motifRefus = null,
    ) {
    }

    /** @return array{debut: string, fin: string, disponible: bool, motif: string|null} */
    public function jsonSerialize(): array
    {
        return [
            'debut' => $this->debut->format('Y-m-d'),
            'fin' => $this->fin->format('Y-m-d'),
            'disponible' => null === $this->motifRefus,
            'motif' => $this->motifRefus,
        ];
    }
}
