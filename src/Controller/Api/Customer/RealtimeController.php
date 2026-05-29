<?php

declare(strict_types=1);

namespace App\Controller\Api\Customer;

use App\Api\ApiResponse;
use App\Service\Realtime\RealtimeSubscribeTokenService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/customer/realtime')]
final class RealtimeController extends AbstractCustomerApiController
{
    #[Route('/config', name: 'api_customer_realtime_config', methods: ['GET'])]
    public function config(
        Request $request,
        #[Autowire('%env(REALTIME_WS_PUBLIC_URL)%')]
        string $publicWsUrl,
    ): JsonResponse {
        $this->requireCustomerApi();

        return ApiResponse::success([
            'websocketUrl' => $this->resolvePublicWsUrl($publicWsUrl, $request),
            'enabled' => $publicWsUrl !== '',
            'events' => [
                'order.created',
                'order.status_changed',
                'payment.completed',
                'stock.updated',
                'products.updated',
                'cart.updated',
                'orders.updated',
            ],
        ]);
    }

    #[Route('/token', name: 'api_customer_realtime_token', methods: ['GET'])]
    public function token(
        RealtimeSubscribeTokenService $tokens,
        Request $request,
        #[Autowire('%env(REALTIME_WS_PUBLIC_URL)%')]
        string $publicWsUrl,
    ): JsonResponse {
        $this->requireCustomerApi();

        if ($publicWsUrl === '') {
            return ApiResponse::error('Realtime is not enabled.', 503);
        }

        $user = $this->requireUser();

        try {
            $subscribeToken = $tokens->issueForUser($user);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('Realtime is not configured.', 503);
        }

        return ApiResponse::success([
            'subscribeToken' => $subscribeToken,
            'expiresIn' => 3600,
            'websocketUrl' => $this->resolvePublicWsUrl($publicWsUrl, $request),
        ]);
    }

    private function resolvePublicWsUrl(string $url, Request $request): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!\is_array($parts) || !isset($parts['host'])) {
            return $url;
        }

        $host = strtolower((string) $parts['host']);
        if ($host !== 'localhost' && $host !== '127.0.0.1') {
            return $url;
        }

        $requestHost = $request->getHost();
        if ($requestHost === '' || $requestHost === 'localhost' || $requestHost === '127.0.0.1') {
            return $url;
        }

        $parts['host'] = $requestHost;
        $scheme = $parts['scheme'] ?? 'ws';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return sprintf('%s://%s%s%s%s', $scheme, $parts['host'], $port, $path, $query);
    }
}
