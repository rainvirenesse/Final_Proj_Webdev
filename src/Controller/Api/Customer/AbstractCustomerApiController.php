<?php

namespace App\Controller\Api\Customer;

use App\Api\ApiResponse;
use App\Entity\User;
use App\Exception\ProductOutOfStockException;
use App\Security\Voter\CustomerApiVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;

abstract class AbstractCustomerApiController extends AbstractController
{
    protected function requireCustomerApi(): void
    {
        $this->denyAccessUnlessGranted(CustomerApiVoter::CUSTOMER_API);
    }

    protected function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{productId: int, quantity: int}
     */
    protected function parseCartItemPayload(array $data): array
    {
        $rawId = $data['productId'] ?? $data['product_id'] ?? null;
        if ($rawId === null || $rawId === '') {
            throw new \InvalidArgumentException('productId is required. Use GET /api/products to list valid product ids.');
        }
        if (!is_numeric($rawId) || (int) $rawId < 1) {
            throw new \InvalidArgumentException('productId must be a positive integer.');
        }

        $quantity = $data['quantity'] ?? 1;
        if (!is_numeric($quantity) || (int) $quantity < 1) {
            throw new \InvalidArgumentException('quantity must be a positive integer.');
        }

        return [
            'productId' => (int) $rawId,
            'quantity' => (int) $quantity,
        ];
    }

    protected function validationError(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $path = $violation->getPropertyPath() ?: 'body';
            $errors[$path] = $violation->getMessage();
        }

        return ApiResponse::error('Validation failed.', 422, ['violations' => $errors]);
    }

    protected function mapException(\Throwable $e): JsonResponse
    {
        if ($e instanceof ProductOutOfStockException) {
            return ApiResponse::simpleError($e->getMessage(), 400);
        }

        if ($e instanceof \InvalidArgumentException) {
            return ApiResponse::error($e->getMessage(), 400);
        }

        return ApiResponse::error('An unexpected error occurred.', 500);
    }

    protected function mapCartAddException(\Throwable $e): JsonResponse
    {
        if ($e instanceof ProductOutOfStockException) {
            return ApiResponse::simpleError($e->getMessage(), 400);
        }

        if ($e instanceof \InvalidArgumentException) {
            $status = str_contains($e->getMessage(), 'was not found') ? 404 : 400;

            return ApiResponse::error($e->getMessage(), $status);
        }

        return ApiResponse::error('An unexpected error occurred.', 500);
    }
}
