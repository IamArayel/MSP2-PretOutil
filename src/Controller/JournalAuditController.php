<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\LigneJournal;
use App\Enum\ActionAudit;
use App\Repository\AuditLogRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran E9 Journal d'audit (US10), en lecture seule, réservé à l'administrateur.
 * Protégé par access_control (^/admin) et par IsGranted.
 */
#[IsGranted('ROLE_ADMIN')]
final class JournalAuditController extends AbstractController
{
    #[Route('/admin/journal', name: 'app_journal_audit', methods: ['GET'])]
    public function index(
        AuditLogRepository $auditLogRepository,
        UtilisateurRepository $utilisateurRepository,
        #[MapQueryParameter] string $action = '',
        #[MapQueryParameter] int $page = 1,
    ): Response {
        // Valeur reçue comparée à une liste fermée : jamais transmise telle quelle à MongoDB.
        $filtre = ActionAudit::tryFrom($action);
        $entrees = $auditLogRepository->lister($filtre, $page);

        // Noms des acteurs lus dans PostgreSQL, en une requête pour toute la page.
        $ids = array_values(array_filter(array_map(
            static fn ($entree): ?int => $entree->getActeur()->getId(),
            $entrees->elements,
        )));
        $noms = $utilisateurRepository->nomsParIdentifiant($ids);

        return $this->render('journal_audit/index.html.twig', [
            'page' => $entrees,
            'lignes' => array_map(static fn ($entree): LigneJournal => new LigneJournal($entree, $noms), $entrees->elements),
            'actions' => ActionAudit::cases(),
            'filtre' => $filtre,
            'filtre_inconnu' => '' !== $action && null === $filtre,
        ]);
    }
}
