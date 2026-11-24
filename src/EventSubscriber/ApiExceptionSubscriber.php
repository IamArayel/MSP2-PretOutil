<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Erreurs de l'API au format JSON ({"erreur": "..."}), avec le bon code HTTP, au lieu
 * de la page d'erreur HTML. Seuls les messages des erreurs de saisie (400) sont repris ;
 * les autres sont génériques, pour ne rien révéler du fonctionnement interne.
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    private const array MESSAGES = [
        Response::HTTP_NOT_FOUND => 'Objet introuvable.',
        Response::HTTP_METHOD_NOT_ALLOWED => 'Méthode non autorisée : l\'API est en lecture seule (GET).',
        Response::HTTP_TOO_MANY_REQUESTS => 'Trop de requêtes. Réessayez dans quelques instants.',
    ];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'versJson'];
    }

    public function versJson(ExceptionEvent $evenement): void
    {
        if (!str_starts_with($evenement->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $evenement->getThrowable();
        $statut = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
        $entetes = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        $message = match (true) {
            $exception instanceof BadRequestHttpException => $exception->getMessage(),
            default => self::MESSAGES[$statut] ?? 'Erreur interne.',
        };

        $reponse = new JsonResponse(['erreur' => $message], $statut, $entetes);
        $reponse->setEncodingOptions($reponse->getEncodingOptions() | \JSON_UNESCAPED_UNICODE);
        $evenement->setResponse($reponse);
    }
}
