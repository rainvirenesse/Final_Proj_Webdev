<?php
// src/Controller/GoogleAuthController.php

namespace App\Controller;

use App\Entity\User;                                                          // ✅ add
use Doctrine\ORM\EntityManagerInterface;                                      // ✅ add
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;  // ✅ add
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;                            // ✅ add
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;                                 // ✅ add
use Symfony\Component\Routing\Annotation\Route;

class GoogleAuthController extends AbstractController
{
    #[Route('/auth/google', name: 'google_auth_start')]
    public function start(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient('google')->redirect(['email', 'profile']);
    }

    #[Route('/auth/google/callback', name: 'google_auth_callback')]
    public function callback(): RedirectResponse
    {
        return $this->redirectToRoute('login');
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): never
    {
        throw new \LogicException('This method should never be reached.');
    }

    #[Route('/api/auth/google/mobile', name: 'google_auth_mobile', methods: ['POST'])]
    public function mobileLogin(
        Request $request,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $idToken = $data['idToken'] ?? null;

        if (!$idToken) {
            return $this->json(['message' => 'Missing idToken'], 400);
        }

        $googleResponse = file_get_contents(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken)
        );

        if (!$googleResponse) {
            return $this->json(['message' => 'Failed to verify token'], 401);
        }

        $payload = json_decode($googleResponse, true);

        if ($payload['aud'] !== $_ENV['GOOGLE_CLIENT_ID']) {
            return $this->json(['message' => 'Invalid token audience'], 401);
        }

        $email = $payload['email'] ?? null;
        if (!$email) {
            return $this->json(['message' => 'Email not found in token'], 401);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword('');
            $user->setRoles(['ROLE_USER']);
            $em->persist($user);
            $em->flush();
        }

        $token = $jwtManager->create($user);
        return $this->json(['token' => $token]);
    }
}