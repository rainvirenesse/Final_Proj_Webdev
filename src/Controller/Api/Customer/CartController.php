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
        $this->denyAccessUnlessGranted('ROLE_USER');
        $cart = $this->cartService->getOrCreateCart($this->requireUser());

        return ApiResponse::success($this->cartService->serializeCart($cart));
    }

    #[Route('/items', name: 'api_customer_cart_add_item', methods: ['POST'])]
    public function addItem(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $data = $this->decodeJson($request);

        $violations = $this->validator->validate($data, new Assert\Collection([
            'productId' => [new Assert\NotNull(), new Assert\Type('integer'), new Assert\Positive()],
            'quantity' => [new Assert\Optional([new Assert\Type('integer'), new Assert\Positive()])],
        ]));
        if (\count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $item = $this->cartService->addItem(
                $this->requireUser(),
                (int) $data['productId'],
                (int) ($data['quantity'] ?? 1)
            );
            $cart = $item->getCart();

            return ApiResponse::created([
                'itemId' => $item->getId(),
                'cart' => $cart ? $this->cartService->serializeCart($cart) : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    #[Route('/items/{id}', name: 'api_customer_cart_update_item', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateItem(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $data = $this->decodeJson($request);

        $violations = $this->validator->validate($data, new Assert\Collection([
            'quantity' => [new Assert\NotNull(), new Assert\Type('integer'), new Assert\Positive()],
        ]));
        if (\count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $item = $this->cartService->updateItemQuantity(
                $this->requireUser(),
                $id,
                (int) $data['quantity']
            );
            $cart = $item->getCart();

            return ApiResponse::success([
                'itemId' => $item->getId(),
                'cart' => $cart ? $this->cartService->serializeCart($cart) : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    #[Route('/items/{id}', name: 'api_customer_cart_delete_item', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteItem(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        try {
            $this->cartService->removeItem($this->requireUser(), $id);
            $cart = $this->cartService->getOrCreateCart($this->requireUser());

            return ApiResponse::success($this->cartService->serializeCart($cart));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    #[Route('', name: 'api_customer_cart_clear', methods: ['DELETE'])]
    public function clear(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->cartService->clearCart($this->requireUser());

        return ApiResponse::success(['cleared' => true]);
    }
}
