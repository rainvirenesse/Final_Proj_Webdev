<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Shared signup flow for web and API: hash password, unverified flag, token, verification email.
 */
class UserRegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerificationService $emailVerificationService,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @throws \InvalidArgumentException if email or username is already in use
     */
    public function register(User $user, string $plainPassword): void
    {
        $email = $user->getEmail();
        $username = $user->getUsername();
        if ($email && $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
            throw new \InvalidArgumentException('This email is already registered.');
        }
        if ($username && $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username])) {
            throw new \InvalidArgumentException('This username is already taken.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setRoles(['ROLE_USER']);
        if (!$user->getStatus()) {
            $user->setStatus(User::STATUS_ACTIVE);
        }

        $token = $this->emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($token);
        $user->setIsVerified(false);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
    }
}
