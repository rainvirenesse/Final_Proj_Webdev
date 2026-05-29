<?php
// src/Controller/GoogleAuthController.php

namespace App\Controller;

use App\Entity\User;                                                          // ✅ add
use Doctrine\ORM\EntityManagerInterface;                                      // ✅ add
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;  // ✅ add
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;                            // ✅ add
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;                                 // ✅ add
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
        JWTTokenManagerInterface $jwtManager,
        UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%env(default:google_client_id_default:GOOGLE_CLIENT_ID)%')]
        string $googleWebClientId,
        #[Autowire('%env(default:google_android_client_id_default:GOOGLE_ANDROID_CLIENT_ID)%')]
        ?string $googleAndroidClientId = null,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $idToken = $data['idToken'] ?? null;

        if (!$idToken) {
            return $this->json(['message' => 'Missing idToken'], 400);
        }

        $googleResponse = @file_get_contents(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken)
        );

        if (!$googleResponse) {
            return $this->json(['message' => 'Failed to verify token'], 401);
        }

        $payload = json_decode($googleResponse, true);
        if (!\is_array($payload)) {
            return $this->json(['message' => 'Invalid token verification response'], 401);
        }

        $aud = (string) ($payload['aud'] ?? '');
        $allowedAudiences = array_filter([$googleWebClientId, $googleAndroidClientId]);
        if ($aud === '' || !\in_array($aud, $allowedAudiences, true)) {
            return $this->json(['message' => 'Invalid token audience', 'debug_aud' => $aud,                          
        'debug_allowed' => $allowedAudiences,], 401);
        }

        $email = $payload['email'] ?? null;
        if (!$email) {
            return $this->json(['message' => 'Email not found in token'], 401);
        }
        if (($payload['email_verified'] ?? 'false') !== 'true') {
            return $this->json(['message' => 'Google account email is not verified.'], 401);
        }

        try {
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user) {
                $user = new User();
                $user->setEmail($email);
                $user->setUsername($this->generateUniqueUsername($email, $em));
                $user->setRoles(['ROLE_CUSTOMER']);
                $user->setIsVerified(true);
                $user->setVerificationToken(null);

                // Google-auth users don't use local password login; store a secure random hash.
                $randomPassword = bin2hex(random_bytes(32));
                $user->setPassword($passwordHasher->hashPassword($user, $randomPassword));

                $em->persist($user);
                $em->flush();
            }

            $roles = $user->getRoles();
            if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_STAFF', $roles, true)) {
                return $this->json([
                    'message' => 'This login is for customer accounts only. Staff and admin must use the web dashboard.',
                ], 403);
            }
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'Google login failed while provisioning account.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $token = $jwtManager->create($user);
        return $this->json([
            'status' => 'success',
            'code' => 200,
            'token' => $token,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                    'roles' => $user->getRoles(),
                ],
            ],
        ]);
    }

    private function generateUniqueUsername(string $email, EntityManagerInterface $em): string
    {
        $base = strtolower((string) preg_replace('/[^a-zA-Z0-9_]/', '_', explode('@', $email)[0] ?? 'customer'));
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'customer';
        }

        $username = substr($base, 0, 50);
        $i = 1;
        while ($em->getRepository(User::class)->findOneBy(['username' => $username])) {
            $suffix = '_' . $i++;
            $username = substr($base, 0, 50 - strlen($suffix)) . $suffix;
        }
        return $username;
    }
}
