<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\RoleCompte;
use App\Exception\ModificationCompteImpossibleException;
use App\Repository\UtilisateurRepository;
use App\Service\CompteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran E8 Gestion des comptes (US9), réservé à l'administrateur.
 *
 * Deux protections : access_control (^/admin) et IsGranted. Les actions sont en POST,
 * avec un jeton CSRF propre à l'action et au compte ; les règles sont dans CompteService.
 */
#[Route('/admin/comptes')]
#[IsGranted('ROLE_ADMIN')]
final class GestionComptesController extends AbstractController
{
    public function __construct(private readonly CompteService $compteService)
    {
    }

    #[Route('', name: 'app_gestion_comptes', methods: ['GET'])]
    public function index(UtilisateurRepository $utilisateurRepository, #[MapQueryParameter] int $page = 1): Response
    {
        $comptes = $utilisateurRepository->listerPourGestion($page);
        $roleDe = [];
        foreach ($comptes->elements as $compte) {
            $roleDe[$compte->getId()] = RoleCompte::de($compte);
        }

        return $this->render('gestion_comptes/index.html.twig', [
            'page' => $comptes,
            'role_de' => $roleDe,
            'roles' => RoleCompte::cases(),
        ]);
    }

    #[Route('/{id}/role', name: 'app_gestion_comptes_role', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function role(Utilisateur $compte, Request $request, #[CurrentUser] Utilisateur $administrateur): Response
    {
        $role = RoleCompte::tryFrom($request->getPayload()->getString('role'));

        if ($this->jetonValide('role-compte-'.$compte->getId(), $request)) {
            if (null === $role) {
                $this->addFlash('erreur', 'Rôle inconnu.');
            } else {
                $this->executer(
                    fn () => $this->compteService->changerRole($compte, $role, $administrateur),
                    \sprintf('%s est maintenant %s.', $this->nom($compte), mb_strtolower($role->libelle())),
                );
            }
        }

        return $this->retour($request);
    }

    #[Route('/{id}/desactiver', name: 'app_gestion_comptes_desactiver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function desactiver(Utilisateur $compte, Request $request, #[CurrentUser] Utilisateur $administrateur): Response
    {
        if ($this->jetonValide('desactiver-compte-'.$compte->getId(), $request)) {
            $this->executer(
                fn () => $this->compteService->desactiver($compte, $administrateur),
                \sprintf('Le compte de %s est désactivé.', $this->nom($compte)),
            );
        }

        return $this->retour($request);
    }

    #[Route('/{id}/reactiver', name: 'app_gestion_comptes_reactiver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reactiver(Utilisateur $compte, Request $request, #[CurrentUser] Utilisateur $administrateur): Response
    {
        if ($this->jetonValide('reactiver-compte-'.$compte->getId(), $request)) {
            $this->executer(
                fn () => $this->compteService->reactiver($compte, $administrateur),
                \sprintf('Le compte de %s est réactivé.', $this->nom($compte)),
            );
        }

        return $this->retour($request);
    }

    /** Appelle le service et affiche le succès, ou le refus métier tel quel. */
    private function executer(callable $action, string $succes): void
    {
        try {
            $action();
            $this->addFlash('succes', $succes);
        } catch (ModificationCompteImpossibleException $e) {
            $this->addFlash('erreur', $e->getMessage());
        }
    }

    private function jetonValide(string $identifiant, Request $request): bool
    {
        if ($this->isCsrfTokenValid($identifiant, $request->getPayload()->getString('_token'))) {
            return true;
        }

        $this->addFlash('erreur', 'La page a expiré. Merci de réessayer.');

        return false;
    }

    /** Retour à la page de la liste d'où vient l'action (Post/Redirect/Get). */
    private function retour(Request $request): Response
    {
        $page = max(1, $request->getPayload()->getInt('page', 1));

        return $this->redirectToRoute('app_gestion_comptes', $page > 1 ? ['page' => $page] : [], Response::HTTP_SEE_OTHER);
    }

    private function nom(Utilisateur $compte): string
    {
        return $compte->getPrenom().' '.$compte->getNom();
    }
}
