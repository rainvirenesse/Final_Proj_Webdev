<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class ActivityLogSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof User) {
            $this->logActivity($user, 'LOGIN', null, null, 'User logged in');
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token) {
            $user = $token->getUser();
            if ($user instanceof User) {
                $this->logActivity($user, 'LOGOUT', null, null, 'User logged out');
                
                // Add success message to session
                $request = $this->requestStack->getCurrentRequest();
                if ($request && $request->hasSession()) {
                    $request->getSession()->getFlashBag()->add('success', 'You have been logged out successfully.');
                }
            }
        }
    }

    private function logActivity(User $user, string $action, ?string $entityType, ?int $entityId, string $description): void
    {
        try {
            $log = new ActivityLog();
            $log->setUser($user);
            $log->setAction($action);
            $log->setEntityType($entityType);
            $log->setEntityId($entityId);
            $log->setDescription($description);
            $log->setUserRoles($user->getRoles());

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Silently fail to avoid breaking the application
        }
    }
}

