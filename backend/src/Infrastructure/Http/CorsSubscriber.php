<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CorsSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, array{0: string, 1?: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['handlePreflight', 100],
            KernelEvents::RESPONSE => ['addCorsHeaders', -10],
        ];
    }

    public function handlePreflight(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ('OPTIONS' !== $request->getMethod()) {
            return;
        }

        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $this->applyCorsHeaders($response, $request);

        $event->setResponse($response);
    }

    public function addCorsHeaders(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->applyCorsHeaders($event->getResponse(), $event->getRequest());
    }

    private function applyCorsHeaders(Response $response, Request $request): void
    {
        $origin = $request->headers->get('Origin');

        if (null === $origin || '' === $origin) {
            return;
        }

        $headers = $response->headers;

        $headers->set('Access-Control-Allow-Origin', $origin);
        $headers->set('Access-Control-Allow-Credentials', 'true');
        $headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $headers->set('Access-Control-Allow-Headers', $request->headers->get('Access-Control-Request-Headers', '*'));
        $headers->set('Access-Control-Max-Age', '86400');
        $headers->set('Vary', 'Origin', false);
    }
}
