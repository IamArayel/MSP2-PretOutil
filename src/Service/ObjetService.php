<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ObjetDto;
use App\Entity\Objet;
use App\Entity\Utilisateur;
use App\Enum\ActionAudit;
use App\Exception\ObjetDejaReserveException;
use App\Exception\PhotoIllisibleException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion du catalogue (US6, écran E7). Le contrôleur vérifie les droits (ObjetVoter)
 * et transmet un DTO validé ; les règles de création et de retrait sont ici.
 *
 * Photos (#106) : la nouvelle photo est écrite avant l'enregistrement en base, effacée si celui-ci
 * échoue ; l'ancienne n'est effacée qu'après l'enregistrement réussi. Aucun objet ne pointe vers
 * une photo absente.
 */
class ObjetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $auditLogger,
        private readonly StockagePhotoObjet $stockagePhoto,
    ) {
    }

    /**
     * @throws PhotoIllisibleException si la photo envoyée ne peut pas être décodée
     */
    public function creer(ObjetDto $dto, Utilisateur $gestionnaire): Objet
    {
        $objet = new Objet();
        // Photo d'abord : si elle est illisible, l'objet n'a pas été touché
        $photo = $this->appliquerPhoto($objet, $dto);
        $this->appliquer($objet, $dto);

        $this->entityManager->persist($objet);
        $this->enregistrer($photo);

        $this->auditLogger->journaliser(ActionAudit::ObjetCree, $gestionnaire, 'objet', $objet->getId(), [
            'nom' => $objet->getNom(),
            'categorie' => $objet->getCategorie()->getLibelle(),
            'etat' => $objet->getEtat()->value,
        ]);

        return $objet;
    }

    /**
     * @throws PhotoIllisibleException si la photo envoyée ne peut pas être décodée
     */
    public function modifier(Objet $objet, ObjetDto $dto, Utilisateur $gestionnaire): void
    {
        $etatAvant = $objet->getEtat();
        $photoAvant = $objet->getImage();
        // Photo d'abord : si elle est illisible, l'objet n'a pas été touché
        $photo = $this->appliquerPhoto($objet, $dto);
        $this->appliquer($objet, $dto);

        $this->enregistrer($photo);
        if ($photoAvant !== $objet->getImage()) {
            $this->stockagePhoto->supprimer($photoAvant);
        }

        $details = ['nom' => $objet->getNom()];
        if ($etatAvant !== $objet->getEtat()) {
            $details['etat'] = ['avant' => $etatAvant->value, 'apres' => $objet->getEtat()->value];
        }
        if ($photoAvant !== $objet->getImage()) {
            $details['photo'] = null === $objet->getImage() ? 'retirée' : (null === $photoAvant ? 'ajoutée' : 'remplacée');
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
     * Remise au catalogue d'un objet désactivé (#111) : il redevient réservable, avec les règles
     * habituelles de ReservationService. Sans effet, et sans écriture, sur un objet déjà actif.
     */
    public function reactiver(Objet $objet, Utilisateur $gestionnaire): void
    {
        if ($objet->isActif()) {
            return;
        }

        $objet->setActif(true);
        $this->entityManager->flush();

        $this->auditLogger->journaliser(ActionAudit::ObjetReactive, $gestionnaire, 'objet', $objet->getId(), [
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
        $photo = $objet->getImage();
        $details = ['nom' => $objet->getNom(), 'categorie' => $objet->getCategorie()->getLibelle()];

        try {
            $this->entityManager->remove($objet);
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException $e) {
            throw new ObjetDejaReserveException($e);
        }

        $this->stockagePhoto->supprimer($photo);

        $this->auditLogger->journaliser(ActionAudit::ObjetSupprime, $gestionnaire, 'objet', $id, $details);
    }

    /**
     * Nouvelle photo écrite dans le dossier, ou photo retirée. Renvoie le nom de la photo écrite,
     * à effacer si l'enregistrement en base échoue.
     */
    private function appliquerPhoto(Objet $objet, ObjetDto $dto): ?string
    {
        if (null !== $dto->photo) {
            $nom = $this->stockagePhoto->enregistrer($dto->photo);
            $objet->setImage($nom);

            return $nom;
        }

        if ($dto->supprimerPhoto) {
            $objet->setImage(null);
        }

        return null;
    }

    /** Enregistre en base ; en cas d'échec, la photo qui vient d'être écrite est effacée. */
    private function enregistrer(?string $photoEcrite): void
    {
        try {
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->stockagePhoto->supprimer($photoEcrite);

            throw $e;
        }
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
