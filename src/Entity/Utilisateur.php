<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Personne inscrite : adhérent, gestionnaire ou administrateur (table utilisateur).
 */
#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    /** Empreinte produite par le hasher Symfony, jamais le mot de passe en clair. */
    #[ORM\Column(length: 255)]
    private string $motDePasse;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(length: 100)]
    private string $prenom;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $roles = [];

    /** Faux : la connexion est refusée (RG9). */
    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $creeLe;

    /** Point de départ de la durée de conservation de 3 ans (RGPD). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $derniereActiviteLe = null;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /** L'email est stocké en minuscules (contrainte ck_utilisateur_email_minuscules). */
    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $empreinte): static
    {
        $this->motDePasse = $empreinte;

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

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    /**
     * Tout utilisateur connecté a au moins ROLE_USER ; les autres rôles
     * découlent de role_hierarchy.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

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

    public function getDerniereActiviteLe(): ?\DateTimeImmutable
    {
        return $this->derniereActiviteLe;
    }

    public function setDerniereActiviteLe(?\DateTimeImmutable $derniereActiviteLe): static
    {
        $this->derniereActiviteLe = $derniereActiviteLe;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Aucune donnée sensible temporaire n'est stockée sur l'entité.
    }
}
