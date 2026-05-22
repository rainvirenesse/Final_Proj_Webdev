<?php

namespace App\Controller\Api;

use App\Api\ApiResponse;
use App\Controller\Api\Customer\AbstractCustomerApiController;
use App\Service\Api\CustomerCartService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/api/cart')]
final class MobileCartController extends AbstractCustomerApiController
{
    public function __construct(
        private readonly CustomerCartService $cartService,
    ) {
    }

    #[Route('', name: 'api_cart_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $this->requireCustomerApi();
        $user = $this->requireUser();
        $cart = $this->cartService->getOrCreateCart($user);

        return ApiResponse::success($this->cartService->serializeCart($cart));
    }

    #[Route('/items', name: 'api_cart_add_item', methods: ['POST'])]
    public function addItem(Request $request): JsonResponse
    {
        $this->requireCustomerApi();
        $user = $this->requireUser();
        try {
            $payload = $this->parseCartItemPayload($this->decodeJson($request));
            $this->cartService->addItem(
                $user,
                $payload['productId'],
                $payload['quantity']
            );
            $cart = $this->cartService->getOrCreateCart($user);

            return ApiResponse::created($this->cartService->serializeCart($cart));
        } catch (\Throwable $e) {
            return $this->mapCartAddException($e);
        }
    }

    #[Route('/items/{id}', name: 'api_cart_delete_item', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteItem(int $id): JsonResponse
    {
        $this->requireCustomerApi();
        $user = $this->requireUser();

        try {
            $cart = $this->cartService->removeItem($user, $id);

            return ApiResponse::success($this->cartService->serializeCart($cart));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }
}
