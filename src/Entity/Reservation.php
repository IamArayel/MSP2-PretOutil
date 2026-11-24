<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EtatObjet;
use App\Enum\StatutReservation;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Emprunt d'un objet par un adhérent, de la réservation au retour (table reservation).
 *
 * Les règles de gestion (RG1 à RG7) sont appliquées par ReservationService.
 * L'entité garantit seulement que les champs liés sont renseignés ensemble,
 * comme l'exigent ck_reservation_sortie et ck_reservation_retour.
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
// RG4 : compter les réservations actives d'un adhérent.
#[ORM\Index(name: 'idx_reservation_utilisateur_statut', columns: ['utilisateur_id', 'statut'])]
// Catalogue (US3) : réservations actives qui chevauchent une période. Index partiel : seules
// les réservations actives y figurent. Créé par migration avec « statut IN (...) » ; la condition
// est écrite ici comme PostgreSQL la restitue, pour que Doctrine ne voie pas d'écart.
#[ORM\Index(
    name: 'idx_reservation_dates_actives',
    columns: ['date_debut', 'date_fin'],
    options: ['where' => "((statut)::text = ANY ((ARRAY['confirmee'::character varying, 'en_cours'::character varying])::text[]))"],
)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Adhérent qui réserve. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Utilisateur $utilisateur;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Objet $objet;

    /** Premier jour de la période, inclus. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dateDebut;

    /** Dernier jour de la période, inclus. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dateFin;

    #[ORM\Column(length: 20, enumType: StatutReservation::class, options: ['default' => 'confirmee'])]
    private StatutReservation $statut = StatutReservation::Confirmee;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $creeLe;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sortieLe = null;

    /** Gestionnaire qui a validé la sortie. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?Utilisateur $sortiePar = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $retourLe = null;

    /** Gestionnaire qui a enregistré le retour. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?Utilisateur $retourPar = null;

    #[ORM\Column(length: 20, nullable: true, enumType: EtatObjet::class)]
    private ?EtatObjet $etatRetour = null;

    public function __construct(
        Utilisateur $utilisateur,
        Objet $objet,
        \DateTimeImmutable $dateDebut,
        \DateTimeImmutable $dateFin,
    ) {
        $this->utilisateur = $utilisateur;
        $this->objet = $objet;
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): Utilisateur
    {
        return $this->utilisateur;
    }

    public function getObjet(): Objet
    {
        return $this->objet;
    }

    public function getDateDebut(): \DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function getDateFin(): \DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function getStatut(): StatutReservation
    {
        return $this->statut;
    }

    public function setStatut(StatutReservation $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function getSortieLe(): ?\DateTimeImmutable
    {
        return $this->sortieLe;
    }

    public function getSortiePar(): ?Utilisateur
    {
        return $this->sortiePar;
    }

    /** Date et gestionnaire de sortie sont toujours renseignés ensemble. */
    public function enregistrerSortie(Utilisateur $gestionnaire, \DateTimeImmutable $le): static
    {
        $this->sortiePar = $gestionnaire;
        $this->sortieLe = $le;

        return $this;
    }

    public function getRetourLe(): ?\DateTimeImmutable
    {
        return $this->retourLe;
    }

    public function getRetourPar(): ?Utilisateur
    {
        return $this->retourPar;
    }

    public function getEtatRetour(): ?EtatObjet
    {
        return $this->etatRetour;
    }

    /** Date, gestionnaire et état de retour sont toujours renseignés ensemble. */
    public function enregistrerRetour(Utilisateur $gestionnaire, \DateTimeImmutable $le, EtatObjet $etat): static
    {
        $this->retourPar = $gestionnaire;
        $this->retourLe = $le;
        $this->etatRetour = $etat;

        return $this;
    }
}
