<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\InscriptionDto;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Exception\EmailDejaUtiliseException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Création d'un compte adhérent (US1). Le contrôleur ne fait que transmettre
 * un DTO déjà validé : les règles de création sont ici.
 */
class InscriptionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @throws EmailDejaUtiliseException si l'email est pris entre la validation et l'enregistrement
     */
    public function inscrire(InscriptionDto $dto): Utilisateur
    {
        $telephone = trim((string) $dto->telephone);

        $utilisateur = (new Utilisateur())
            ->setEmail((string) $dto->email) // normalisé en minuscules par l'entité
            ->setPrenom(trim((string) $dto->prenom))
            ->setNom(trim((string) $dto->nom))
            ->setTelephone('' === $telephone ? null : $telephone)
            // Aucun rôle stocké : getRoles() ajoute toujours ROLE_USER (adhérent)
            ->setRoles([])
            // Compte actif dès la création (MVP, pas de validation par l'administrateur)
            ->setActif(true);

        $utilisateur->setMotDePasse(
            $this->passwordHasher->hashPassword($utilisateur, (string) $dto->motDePasse),
        );

        try {
            $this->entityManager->persist($utilisateur);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new EmailDejaUtiliseException($e);
        }

        $this->auditLogger->journaliser(
            ActionAudit::CompteCree,
            $utilisateur,
            'utilisateur',
            $utilisateur->getId(),
        );

        return $utilisateur;
    }
}
