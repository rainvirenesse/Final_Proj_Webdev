<?php

declare(strict_types=1);

namespace App\Service\Mercure;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Mercure topics must match between publisher and subscriber.
 * Uses the current HTTP host when available (LAN/mobile), else APP_URL.
 */
final class MercureTopicFactory
{
    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire('%app.url%')]
        private readonly string $fallbackBase,
    ) {
    }

    public function orders(): string
    {
        return $this->baseUrl().'/orders';
    }

    public function orderStatus(int $orderId): string
    {
        return $this->orders().'/'.$orderId.'/status';
    }

    private function baseUrl(): string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request !== null) {
            return $request->getSchemeAndHttpHost();
        }

        return rtrim($this->fallbackBase, '/');
    }
}
