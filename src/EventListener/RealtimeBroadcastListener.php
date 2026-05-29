<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ActivityLog;
use App\Entity\CustomerOrder;
use App\Entity\Payment;
use App\Entity\Product;
use App\Service\Realtime\WebSocketBroadcastService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
final class RealtimeBroadcastListener
{
    public function __construct(
        private readonly WebSocketBroadcastService $realtime,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof CustomerOrder) {
            $this->realtime->publishOrderCreated($entity);

            return;
        }

        if ($entity instanceof Payment && $entity->getStatus() === Payment::STATUS_COMPLETED) {
            $order = $entity->getOrder();
            if ($order instanceof CustomerOrder) {
                $this->realtime->publishPaymentCompleted($order, $entity);
            }

            return;
        }

        if ($entity instanceof ActivityLog) {
            $this->realtime->publishActivityLogged($entity);
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $uow = $args->getObjectManager()->getUnitOfWork();
        $changeSet = $uow->getEntityChangeSet($entity);

        if ($entity instanceof CustomerOrder) {
            $this->handleOrderUpdate($entity, $changeSet);

            return;
        }

        if ($entity instanceof Product && isset($changeSet['stock'])) {
            $previous = $changeSet['stock'][0];
            $this->realtime->publishStockUpdated($entity, \is_int($previous) ? $previous : (int) $previous);
        }
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     */
    private function handleOrderUpdate(CustomerOrder $order, array $changeSet): void
    {
        if (isset($changeSet['status'])) {
            $previous = $changeSet['status'][0];
            $this->realtime->publishOrderStatusChanged(
                $order,
                \is_string($previous) ? $previous : null,
            );
        }

        if (isset($changeSet['paymentStatus'])) {
            $new = $changeSet['paymentStatus'][1];
            if ($new === CustomerOrder::PAYMENT_PAID) {
                $this->realtime->publishPaymentCompleted($order);
            } elseif (!isset($changeSet['status'])) {
                $this->realtime->publishOrderStatusChanged($order);
            }
        }
    }
}
