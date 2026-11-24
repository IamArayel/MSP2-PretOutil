<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Objet;
use App\Service\CatalogueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API REST des disponibilités (US11, cahier des charges section 6.5), en lecture seule
 * et publique comme le catalogue. Documentation : docs/conception/api.md.
 *
 * Le contrôleur lit les paramètres et renvoie du JSON ; les règles sont celles de
 * ReservationService, appelées par CatalogueService. Les erreurs sont mises au format
 * JSON par App\EventSubscriber\ApiExceptionSubscriber.
 */
final class DisponibiliteController extends AbstractController
{
    public function __construct(
        private readonly CatalogueService $catalogueService,
        #[Autowire(service: 'limiter.api_disponibilites')]
        private readonly RateLimiterFactoryInterface $limiteur,
    ) {
    }

    #[Route('/api/objets/{id}/disponibilites', name: 'api_objet_disponibilites', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function disponibilites(Objet $objet, Request $request): JsonResponse
    {
        $this->limiterLeDebit($request);

        // Un objet retiré du catalogue n'existe plus pour l'API, comme pour l'écran E4.
        if (!$objet->isActif()) {
            throw new NotFoundHttpException();
        }

        [$debut, $fin] = $this->lirePeriode($request);

        $reponse = $this->json($this->catalogueService->disponibilites($objet, $debut, $fin));
        // Accents lisibles ; les protections par défaut de JsonResponse (échappement de < > & ' ") restent actives.
        $reponse->setEncodingOptions($reponse->getEncodingOptions() | \JSON_UNESCAPED_UNICODE);
        // Disponibilités changeantes : jamais servies depuis un cache.
        $reponse->headers->set('Cache-Control', 'no-store');

        return $reponse;
    }

    /** Nombre d'appels limité par adresse IP (cahier des charges, section 6.5). */
    private function limiterLeDebit(Request $request): void
    {
        $limite = $this->limiteur->create($request->getClientIp() ?? 'inconnue')->consume();

        if (!$limite->isAccepted()) {
            throw new TooManyRequestsHttpException($limite->getRetryAfter()->getTimestamp() - time());
        }
    }

    /**
     * Paramètres debut et fin, facultatifs mais fournis ensemble, au format AAAA-MM-JJ.
     *
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private function lirePeriode(Request $request): array
    {
        $debut = $request->query->get('debut');
        $fin = $request->query->get('fin');

        if (null === $debut && null === $fin) {
            return [null, null];
        }
        if (null === $debut || null === $fin) {
            throw new BadRequestHttpException('Les paramètres debut et fin vont ensemble.');
        }

        return [$this->lireDate($debut, 'debut'), $this->lireDate($fin, 'fin')];
    }

    private function lireDate(string $valeur, string $nom): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        // Le format doit être exact : « 2026-02-30 » ou « 2026-12-1 » sont refusés.
        if (false === $date || $date->format('Y-m-d') !== $valeur) {
            throw new BadRequestHttpException(\sprintf('Le paramètre %s doit être une date au format AAAA-MM-JJ.', $nom));
        }

        return $date;
    }
}
