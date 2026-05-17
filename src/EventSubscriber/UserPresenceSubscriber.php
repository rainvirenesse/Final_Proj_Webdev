<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Tracks dashboard login activity: updates lastActivityAt on login and throttled requests;
 * clears it on logout. User list "Account" column uses this for Active / Inactive within the TTL.
 */
final class UserPresenceSubscriber implements EventSubscriberInterface
{
    private const THROTTLE_SECONDS = 60;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuthorizationCheckerInterface $authorizationChecker,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
            LogoutEvent::class => 'onLogout',
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $this->tokenUser($event->getAuthenticationToken());
        if ($user === null) {
            return;
        }

        $this->touchPresenceNow($user);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $this->tokenUser($event->getToken());
        if ($user === null) {
            return;
        }

        $managed = $this->entityManager->find(User::class, $user->getId());
        if ($managed === null) {
            return;
        }

        $managed->setLastActivityAt(null);
        $this->entityManager->flush();
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/admin') && !str_starts_with($path, '/staff')) {
            return;
        }

        if (!$this->authorizationChecker->isGranted('ROLE_STAFF')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $this->tokenUser($token);
        if ($user === null) {
            return;
        }

        $managed = $this->entityManager->find(User::class, $user->getId());
        if ($managed === null) {
            return;
        }

        $now = new \DateTimeImmutable();
        $last = $managed->getLastActivityAt();
        if ($last !== null && ($now->getTimestamp() - $last->getTimestamp()) < self::THROTTLE_SECONDS) {
            return;
        }

        $managed->setLastActivityAt($now);
        $this->entityManager->flush();
    }

    private function touchPresenceNow(User $user): void
    {
        $managed = $this->entityManager->find(User::class, $user->getId());
        if ($managed === null) {
            return;
        }

        $managed->setLastActivityAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function tokenUser(?TokenInterface $token): ?User
    {
        if ($token === null) {
            return null;
        }

        $user = $token->getUser();
        return $user instanceof User ? $user : null;
    }
}
