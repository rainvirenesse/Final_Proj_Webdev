<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Psr\Log\LoggerInterface;

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
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws \InvalidArgumentException if email or username is already in use
     */
    public function register(User $user, string $plainPassword): void
    {
        $email = $user->getEmail();
        $username = $user->getUsername();
        $this->logger->info('UserRegistrationService: Starting registration for email: ' . $email . ', username: ' . $username);
        
        if ($email && $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
            $this->logger->warning('UserRegistrationService: Email already registered: ' . $email);
            throw new \InvalidArgumentException('This email is already registered.');
        }
        if ($username && $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username])) {
            $this->logger->warning('UserRegistrationService: Username already taken: ' . $username);
            throw new \InvalidArgumentException('This username is already taken.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setRoles(['ROLE_CUSTOMER']);
        if (!$user->getStatus()) {
            $user->setStatus(User::STATUS_ACTIVE);
        }

        $token = $this->emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($token);
        $user->setIsVerified(false);

        $this->logger->info('UserRegistrationService: Persisting user to database.');
        $this->entityManager->persist($user);
        
        try {
            $this->entityManager->flush();
            $this->logger->info('UserRegistrationService: Successfully flushed user to database.');
        } catch (\Exception $e) {
            $this->logger->error('UserRegistrationService: Failed to flush user to database: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        
        $this->logger->info('UserRegistrationService: Attempting to send verification email.');
        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
        $this->logger->info('UserRegistrationService: Verification email sent successfully.');
    }
}