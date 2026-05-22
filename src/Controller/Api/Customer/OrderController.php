<?php

namespace App\Controller\Api\Customer;

use App\Api\ApiResponse;
use App\Service\Api\CustomerOrderApiService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/api/customer/orders')]
final class OrderController extends AbstractCustomerApiController
{
    public function __construct(
        private readonly CustomerOrderApiService $orderService,
    ) {
    }

    #[Route('', name: 'api_customer_orders_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->requireCustomerApi();
        $orders = $this->orderService->listForUser($this->requireUser());
        $data = array_map(
            fn ($o) => $this->orderService->serializeOrder($o, false),
            $orders
        );

        return ApiResponse::success($data, 200, ['count' => \count($data)]);
    }

    #[Route('/{id}', name: 'api_customer_orders_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $this->requireCustomerApi();
        $order = $this->orderService->findForUser($this->requireUser(), $id);
        if (!$order) {
            return ApiResponse::error('Order not found.', 404);
        }

        return ApiResponse::success($this->orderService->serializeOrder($order, true));
    }

    #[Route('', name: 'api_customer_orders_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->requireCustomerApi();
        $data = $this->decodeJson($request);
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;

        try {
            $order = $this->orderService->createFromCart($this->requireUser(), $notes);

            return ApiResponse::created($this->orderService->serializeOrder($order, true));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}', name: 'api_customer_orders_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->requireCustomerApi();
        $data = $this->decodeJson($request);

        if (!\array_key_exists('notes', $data)) {
            return ApiResponse::error('Provide "notes" to update.', 400);
        }

        try {
            $order = $this->orderService->updateNotes(
                $this->requireUser(),
                $id,
                $data['notes'] !== null ? trim((string) $data['notes']) : null
            );

            return ApiResponse::success($this->orderService->serializeOrder($order, true));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}', name: 'api_customer_orders_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function cancel(int $id): JsonResponse
    {
        $this->requireCustomerApi();

        try {
            $order = $this->orderService->cancel($this->requireUser(), $id);

            return ApiResponse::success($this->orderService->serializeOrder($order, true));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
