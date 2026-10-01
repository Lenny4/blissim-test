<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\DatabaseException;
use App\Exception\ProductApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Transforme les pannes de nos dépendances externes (API produits, base de données)
 * en une page "service indisponible" (503) lisible par l'utilisateur, au lieu d'une erreur 500.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ServiceUnavailableListener
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $exceptionEvent): void
    {
        $exception = $exceptionEvent->getThrowable();

        if (!$exception instanceof ProductApiException && !$exception instanceof DatabaseException) {
            return;
        }

        $this->logger->critical('Service externe indisponible', ['exception' => $exception]);

        $response = new Response(
            $this->twig->render('error/service_unavailable.html.twig', ['message' => $exception->getMessage()]),
            Response::HTTP_SERVICE_UNAVAILABLE,
            // Indique aux clients/robots qu'ils peuvent réessayer plus tard
            ['Retry-After' => '60'],
        );

        $exceptionEvent->setResponse($response);
    }
}
