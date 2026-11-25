<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EtatObjet;
use App\Repository\ObjetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Article physique prêté par l'association (table objet).
 */
#[ORM\Entity(repositoryClass: ObjetRepository::class)]
class Objet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Categorie $categorie;

    #[ORM\Column(length: 120)]
    private string $nom;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $caracteristiques = null;

    /**
     * Photo de l'objet : nom d'un fichier de assets/images/objets/ (null : vignette de remplacement).
     * La contrainte CHECK de la base n'accepte qu'un nom simple en .webp, sans chemin.
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $image = null;

    /** Hors service : l'objet n'est plus réservable (RG5, RG7). */
    #[ORM\Column(length: 20, enumType: EtatObjet::class, options: ['default' => 'bon'])]
    private EtatObjet $etat = EtatObjet::Bon;

    /** Faux : retiré du catalogue (suppression logique, RG5). */
    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $creeLe;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategorie(): Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(Categorie $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCaracteristiques(): ?string
    {
        return $this->caracteristiques;
    }

    public function setCaracteristiques(?string $caracteristiques): static
    {
        $this->caracteristiques = $caracteristiques;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getEtat(): EtatObjet
    {
        return $this->etat;
    }

    public function setEtat(EtatObjet $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }
}
