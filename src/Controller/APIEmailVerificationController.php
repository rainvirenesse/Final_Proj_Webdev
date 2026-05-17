<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class APIEmailVerificationController extends AbstractController
{
    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verify(Request $request, EmailVerificationService $emailVerificationService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = \is_array($data) ? ($data['token'] ?? null) : null;
        if (!$token || !\is_string($token)) {
            return new JsonResponse(['error' => 'Missing "token" in JSON body.'], 400);
        }

        $user = $emailVerificationService->verifyToken($token);
        if (!$user) {
            return new JsonResponse(['error' => 'Invalid or expired verification token.'], 400);
        }

        return new JsonResponse([
            'message' => 'Email verified successfully.',
            'verified' => true,
            'email' => $user->getEmail(),
        ]);
    }
}
