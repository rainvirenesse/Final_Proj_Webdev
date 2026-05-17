<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uniform JSON for Lexik JWT failures (replaces default e.g. {"code":401,"message":"JWT Token not found"}).
 */
final class LexikJwtFailureResponseSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_jwt_not_found' => 'onJwtNotFound',
            'lexik_jwt_authentication.on_jwt_invalid' => 'onJwtInvalid',
            'lexik_jwt_authentication.on_jwt_expired' => 'onJwtExpired',
        ];
    }

    public function onJwtNotFound(JWTNotFoundEvent $event): void
    {
        $request = $event->getRequest();
        $pathInfo = $request?->getPathInfo() ?? '';
        $normalized = rtrim($pathInfo, '/');

        // For direct browser hits to /api, avoid leaking implementation details.
        if ($normalized === '/api') {
            $event->setResponse(new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND));
            return;
        }

        $event->setResponse($this->json401(
            'JWT token not provided. Send Authorization: Bearer <token> (obtain via POST /api/login).'
        ));
    }

    public function onJwtInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse($this->json401('Invalid JWT token.'));
    }

    public function onJwtExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse($this->json401('JWT token has expired.'));
    }

    private function json401(string $error): JsonResponse
    {
        return new JsonResponse([
            'error' => $error,
            'code' => Response::HTTP_UNAUTHORIZED,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
