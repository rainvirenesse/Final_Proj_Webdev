<?php

namespace App\Controller\Api;

use App\Api\ApiResponse;
use App\Controller\Api\Customer\AbstractCustomerApiController;
use App\Service\Api\CustomerOrderApiService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders')]
final class MobileOrderController extends AbstractCustomerApiController
{
    public function __construct(
        private readonly CustomerOrderApiService $orderService,
    ) {
    }

    #[Route('', name: 'api_orders_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->requireCustomerApi();
        $data = $this->decodeJson($request);
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;

        try {
            $order = $this->orderService->createFromCart($this->requireUser(), $notes);

            return ApiResponse::created(
                $this->orderService->serializeOrder($order, true),
                'Order placed successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
