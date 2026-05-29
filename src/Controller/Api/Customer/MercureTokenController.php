<?php

declare(strict_types=1);

namespace App\Controller\Api\Customer;

use App\Service\Api\CustomerOrderApiService;
use App\Service\Mercure\MercureTopicFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/customer/mercure')]
final class MercureTokenController extends AbstractCustomerApiController
{
    #[Route('/token/{orderId}', name: 'api_customer_mercure_token', methods: ['GET'], requirements: ['orderId' => '\d+'])]
    public function orderStatusToken(
        int $orderId,
        TokenFactoryInterface $defaultTokenFactory,
        MercureTopicFactory $topics,
        CustomerOrderApiService $orderService,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')]
        string $mercurePublicUrl,
    ): JsonResponse {
        $this->requireCustomerApi();
        $order = $orderService->findForUser($this->requireUser(), $orderId);
        if (!$order) {
            return $this->json(['error' => 'Order not found.'], 404);
        }

        $topic = $topics->orderStatus($orderId);

        return $this->json([
            'token' => $defaultTokenFactory->create(
                subscribe: [$topic],
            ),
            'hub' => $mercurePublicUrl,
            'topic' => $topic,
        ]);
    }
}
