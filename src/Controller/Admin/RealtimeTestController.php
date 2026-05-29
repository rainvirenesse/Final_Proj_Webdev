<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Realtime\WebSocketBroadcastService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RealtimeTestController extends AbstractController
{
    #[Route('/admin/realtime/test', name: 'admin_realtime_test', methods: ['POST'])]
    public function test(WebSocketBroadcastService $realtime): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $realtime->publishToStaff('test.ping', [
            'message' => 'Hello from /admin/realtime/test',
        ]);

        return new JsonResponse(['ok' => true]);
    }
}

