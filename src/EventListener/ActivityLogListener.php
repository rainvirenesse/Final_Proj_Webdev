<?php

namespace App\EventListener;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class ActivityLogListener
{
    private EntityManagerInterface $entityManager;
    private TokenStorageInterface $tokenStorage;

    public function __construct(EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage)
    {
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        
        // Skip ActivityLog itself to avoid recursion
        if ($entity instanceof ActivityLog) {
            return;
        }

        // Only log if there's an authenticated user (skip CLI commands, fixtures, etc.)
        $user = $this->getCurrentUser();
        if (!$user) {
            return;
        }

        try {
            $entityType = $this->getEntityType($entity);
            $entityId = $this->getEntityId($entity);
            $description = sprintf('%s created', $entityType);
            $this->logActivity($user, 'CREATE', $entityType, $entityId, $description);
        } catch (\Exception $e) {
            // Silently fail to avoid breaking the application
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        // Skip ActivityLog itself to avoid recursion
        if ($entity instanceof ActivityLog) {
            return;
        }

        // Only log if there's an authenticated user (skip CLI commands, fixtures, etc.)
        $user = $this->getCurrentUser();
        if (!$user) {
            return;
        }

        try {
            $entityType = $this->getEntityType($entity);
            $entityId = $this->getEntityId($entity);
            $description = sprintf('%s updated', $entityType);
            $this->logActivity($user, 'UPDATE', $entityType, $entityId, $description);
        } catch (\Exception $e) {
            // Silently fail to avoid breaking the application
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        
        // Skip ActivityLog itself to avoid recursion
        if ($entity instanceof ActivityLog) {
            return;
        }

        // Only log if there's an authenticated user (skip CLI commands, fixtures, etc.)
        $user = $this->getCurrentUser();
        if (!$user) {
            return;
        }

        try {
            $entityType = $this->getEntityType($entity);
            $entityId = $this->getEntityId($entity);
            $description = sprintf('%s deleted', $entityType);
            $this->logActivity($user, 'DELETE', $entityType, $entityId, $description);
        } catch (\Exception $e) {
            // Silently fail to avoid breaking the application
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

            // Use a separate entity manager to avoid recursion issues
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Silently fail to avoid breaking the application
        }
    }

    private function getCurrentUser(): ?User
    {
        try {
            $token = $this->tokenStorage->getToken();
            if ($token && $token->getUser() instanceof User) {
                return $token->getUser();
            }
        } catch (\Exception $e) {
            // Token might not be available
        }
        return null;
    }

    private function getEntityType($entity): string
    {
        $className = get_class($entity);
        $parts = explode('\\', $className);
        return end($parts);
    }

    private function getEntityId($entity): ?int
    {
        // Try to get ID using common methods
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            return $id !== null ? (int) $id : null;
        }
        
        // Fallback: try to get ID from entity manager metadata
        try {
            $metadata = $this->entityManager->getClassMetadata(get_class($entity));
            $identifier = $metadata->getIdentifier();
            if (!empty($identifier)) {
                $idField = $identifier[0];
                $reflection = new \ReflectionClass($entity);
                if ($reflection->hasProperty($idField)) {
                    $property = $reflection->getProperty($idField);
                    $property->setAccessible(true);
                    $id = $property->getValue($entity);
                    return $id !== null ? (int) $id : null;
                }
            }
        } catch (\Exception $e) {
            // If we can't get the ID, return null
        }
        
        return null;
    }
}

