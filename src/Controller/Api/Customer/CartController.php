<?php

namespace App\Controller\Api\Customer;

use App\Api\ApiResponse;
use App\Service\Api\CustomerCartService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/customer/cart')]
final class CartController extends AbstractCustomerApiController
{
    public function __construct(
        private readonly CustomerCartService $cartService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_customer_cart_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $this->requireCustomerApi();
        $user = $this->requireUser();
        $cart = $this->cartService->getOrCreateCart($user);

        return ApiResponse::success($this->cartService->serializeCart($cart));
    }

    #[Route('/items', name: 'api_customer_cart_add_item', methods: ['POST'])]
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

    #[Route('/items/{id}', name: 'api_customer_cart_update_item', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateItem(int $id, Request $request): JsonResponse
    {
        $this->requireCustomerApi();
        $user = $this->requireUser();
        $data = $this->decodeJson($request);

        $violations = $this->validator->validate($data, new Assert\Collection([
            'quantity' => [new Assert\NotNull(), new Assert\Type('integer'), new Assert\Positive()],
        ]));
        if (\count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $this->cartService->updateItemQuantity($user, $id, (int) $data['quantity']);
            $cart = $this->cartService->getOrCreateCart($user);

            return ApiResponse::success($this->cartService->serializeCart($cart));
        } catch (\Throwable $e) {
            return $this->mapCartAddException($e);
        }
    }

    #[Route('/items/{id}', name: 'api_customer_cart_delete_item', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteItem(int $id): JsonResponse
    {
        $this->requireCustomerApi();

        try {
            $cart = $this->cartService->removeItem($this->requireUser(), $id);

            return ApiResponse::success($this->cartService->serializeCart($cart));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    #[Route('', name: 'api_customer_cart_clear', methods: ['DELETE'])]
    public function clear(): JsonResponse
    {
        $this->requireCustomerApi();
        $this->cartService->clearCart($this->requireUser());

        return ApiResponse::success(['cleared' => true]);
    }
}
