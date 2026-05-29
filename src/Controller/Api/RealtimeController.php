<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ApiResponse;
use App\Entity\User;
use App\Service\Realtime\RealtimeSubscribeTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/realtime')]
#[IsGranted('IS_AUTHENTICATED')]
final class RealtimeController extends AbstractController
{
    #[Route('/config', name: 'api_realtime_config', methods: ['GET'])]
    public function config(
        #[Autowire('%env(REALTIME_WS_PUBLIC_URL)%')]
        string $publicWsUrl,
    ): JsonResponse {
        return ApiResponse::success([
            'websocketUrl' => $publicWsUrl,
            'enabled' => $publicWsUrl !== '',
            'events' => [
                'order.created',
                'order.status_changed',
                'payment.completed',
                'stock.updated',
                'products.updated',
                'activity.logged',
                'cart.updated',
                'orders.updated',
            ],
        ]);
    }

    #[Route('/token', name: 'api_realtime_token', methods: ['GET'])]
    public function token(
        RealtimeSubscribeTokenService $tokens,
        #[Autowire('%env(REALTIME_WS_PUBLIC_URL)%')]
        string $publicWsUrl,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return ApiResponse::error('Unauthorized', 401);
        }

        if ($publicWsUrl === '') {
            return ApiResponse::error('Realtime is not enabled.', 503);
        }

        try {
            $subscribeToken = $tokens->issueForUser($user);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('Realtime is not configured.', 503);
        }

        return ApiResponse::success([
            'subscribeToken' => $subscribeToken,
            'expiresIn' => 3600,
        ]);
    }
}
