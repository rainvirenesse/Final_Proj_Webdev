<?php
// src/Security/GoogleAuthenticator.php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private RouterInterface $router,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'google_auth_callback';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = $googleUser->getEmail();
                $googleId = $googleUser->getId();

                if (!$email) {
                    throw new AuthenticationException('Access denied. Google account email is missing.');
                }

                // Find existing user by email
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

                if (!$user) {
                    // ✅ Provision first-time Google users as default Staff.
                    $user = new User();
                    $user->setEmail($email);
                    $user->setGoogleId($googleId ? (string)$googleId : null);
                    $user->setUsername($this->generateUniqueUsername($email));
                    $user->setRoles(['ROLE_STAFF']);
                    $user->setStatus(User::STATUS_ACTIVE);
                    $user->setIsVerified(true);

                    // User entity requires a password field; use a random hashed value.
                    $plainPassword = bin2hex(random_bytes(16));
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

                    $this->em->persist($user);
                    $this->em->flush();
                } else {
                    // Only ROLE_STAFF users can login with Google.
                    if (
                        in_array('ROLE_ADMIN', $user->getRoles()) ||
                        !in_array('ROLE_STAFF', $user->getRoles())
                    ) {
                        throw new AuthenticationException('Access denied. Only staff members can login with Google.');
                    }

                    if (!$user->getGoogleId()) {
                        $user->setGoogleId($googleId ? (string)$googleId : null);
                    }
                }

                // Auto-verify (Google-provided identity)
                $user->setIsVerified(true);
                $this->em->flush();

                return $user;
            })
        );
    }

    private function generateUniqueUsername(string $email): string
    {
        $local = explode('@', strtolower(trim($email)))[0] ?? 'user';
        $base = preg_replace('/[^a-zA-Z0-9_]+/', '_', $local) ?: 'staff';
        $base = substr($base, 0, 45);

        $repo = $this->em->getRepository(User::class);
        $candidate = $base;
        $i = 1;

        while ($repo->findOneBy(['username' => $candidate])) {
            $candidate = $base . '_' . $i;
            $i++;
        }

        return $candidate;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('staff_customer_order_index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set('google_auth_error', $exception->getMessage());
        return new RedirectResponse($this->router->generate('login'));
    }
}