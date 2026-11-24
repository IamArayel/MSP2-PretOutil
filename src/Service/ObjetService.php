<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ObjetDto;
use App\Entity\Objet;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Exception\ObjetDejaReserveException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion du catalogue (US6, écran E7). Le contrôleur vérifie les droits (ObjetVoter)
 * et transmet un DTO validé ; les règles de création et de retrait sont ici.
 */
class ObjetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function creer(ObjetDto $dto, Utilisateur $gestionnaire): Objet
    {
        $objet = new Objet();
        $this->appliquer($objet, $dto);

        $this->entityManager->persist($objet);
        $this->entityManager->flush();

        $this->auditLogger->journaliser(ActionAudit::ObjetCree, $gestionnaire, 'objet', $objet->getId(), [
            'nom' => $objet->getNom(),
            'categorie' => $objet->getCategorie()->getLibelle(),
            'etat' => $objet->getEtat()->value,
        ]);

        return $objet;
    }

    public function modifier(Objet $objet, ObjetDto $dto, Utilisateur $gestionnaire): void
    {
        $etatAvant = $objet->getEtat();
        $this->appliquer($objet, $dto);
        $this->entityManager->flush();

        $details = ['nom' => $objet->getNom()];
        if ($etatAvant !== $objet->getEtat()) {
            $details['etat'] = ['avant' => $etatAvant->value, 'apres' => $objet->getEtat()->value];
        }

        $this->auditLogger->journaliser(ActionAudit::ObjetModifie, $gestionnaire, 'objet', $objet->getId(), $details);
    }

    /**
     * Suppression logique (RG5) : l'objet sort du catalogue mais reste en base,
     * avec l'historique de ses réservations.
     */
    public function desactiver(Objet $objet, Utilisateur $gestionnaire): void
    {
        if (!$objet->isActif()) {
            return;
        }

        $objet->setActif(false);
        $this->entityManager->flush();

        $this->auditLogger->journaliser(ActionAudit::ObjetDesactive, $gestionnaire, 'objet', $objet->getId(), [
            'nom' => $objet->getNom(),
        ]);
    }

    /**
     * Suppression physique, réservée à un objet jamais réservé (contrôlé par ObjetVoter::SUPPRIMER).
     *
     * @throws ObjetDejaReserveException si une réservation existe malgré tout (accès simultanés)
     */
    public function supprimer(Objet $objet, Utilisateur $gestionnaire): void
    {
        // Relevés avant la suppression : l'identifiant n'existe plus après le flush.
        $id = $objet->getId();
        $details = ['nom' => $objet->getNom(), 'categorie' => $objet->getCategorie()->getLibelle()];

        try {
            $this->entityManager->remove($objet);
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException $e) {
            throw new ObjetDejaReserveException($e);
        }

        $this->auditLogger->journaliser(ActionAudit::ObjetSupprime, $gestionnaire, 'objet', $id, $details);
    }

    private function appliquer(Objet $objet, ObjetDto $dto): void
    {
        $caracteristiques = trim((string) $dto->caracteristiques);

        $objet
            ->setCategorie($dto->categorie)
            ->setNom(trim((string) $dto->nom))
            ->setDescription(trim((string) $dto->description))
            ->setCaracteristiques('' === $caracteristiques ? null : $caracteristiques)
            ->setEtat($dto->etat);
    }
}
