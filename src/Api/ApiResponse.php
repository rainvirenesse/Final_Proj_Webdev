<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponse
{
    public static function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        $body = ['data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $status);
    }

    public static function created(mixed $data, array $meta = []): JsonResponse
    {
        return self::success($data, 201, $meta);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function error(string $message, int $status, array $extra = []): JsonResponse
    {
        return new JsonResponse(
            ['error' => $message, 'code' => $status] + $extra,
            $status
        );
    }
}
