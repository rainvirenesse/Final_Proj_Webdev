<?php

declare(strict_types=1);

namespace App\Controller\Api\Staff;

use App\Api\ApiResponse;
use App\Service\Api\StaffOrderApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/orders')]
#[IsGranted('ROLE_STAFF')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly StaffOrderApiService $orderService,
    ) {
    }

    #[Route('/{id}/approve', name: 'api_orders_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->approve($id);

            return ApiResponse::success([
                'order' => $this->orderService->serializeOrder($order),
                'mercure' => [
                    'status' => 'ready_to_deliver',
                    'orderId' => $order->getId(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
