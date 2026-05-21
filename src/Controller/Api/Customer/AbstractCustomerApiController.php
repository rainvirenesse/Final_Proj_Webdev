<?php

namespace App\Controller\Api\Customer;

use App\Api\ApiResponse;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;

abstract class AbstractCustomerApiController extends AbstractController
{
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
        if ($e instanceof \InvalidArgumentException) {
            return ApiResponse::error($e->getMessage(), 400);
        }

        return ApiResponse::error('An unexpected error occurred.', 500);
    }
}
