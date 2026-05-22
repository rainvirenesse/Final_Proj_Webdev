<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Uniform JSON envelope for all REST API responses.
 */
final class ApiResponse
{
    public static function success(mixed $data, int $status = 200, array $meta = [], ?string $message = null): JsonResponse
    {
        $body = [
            'status' => 'success',
            'code' => $status,
            'data' => $data,
        ];
        if ($message !== null && $message !== '') {
            $body['message'] = $message;
        }
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $status);
    }

    public static function created(mixed $data, ?string $message = 'Created', array $meta = []): JsonResponse
    {
        return self::success($data, 201, $meta, $message);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function error(string $message, int $status, array $extra = []): JsonResponse
    {
        return new JsonResponse(array_merge([
            'status' => 'error',
            'code' => $status,
            'message' => $message,
            'error' => $message,
        ], $extra), $status);
    }

    /**
     * Minimal error body required by mobile/cart clients: {"error": "message"}.
     */
    public static function simpleError(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
