<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Entity\CustomerOrder;
use App\Repository\CustomerOrderRepository;
use App\Service\Mercure\MercureOrderPublisher;
use App\Service\Realtime\WebSocketBroadcastService;
use Doctrine\ORM\EntityManagerInterface;

final class StaffOrderApiService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustomerOrderRepository $orderRepository,
        private readonly CustomerOrderApiService $orderApiService,
        private readonly MercureOrderPublisher $mercure,
        private readonly WebSocketBroadcastService $realtime,
    ) {
    }

    public function approve(int $orderId): CustomerOrder
    {
        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Order not found.');
        }
        if ($order->getStatus() === CustomerOrder::STATUS_CANCELLED) {
            throw new \InvalidArgumentException('Cancelled orders cannot be approved.');
        }
        if ($order->getStatus() === CustomerOrder::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Completed orders cannot be approved.');
        }

        $order->setStatus(CustomerOrder::STATUS_IN_PROGRESS);
        $order->setStaffApprovedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->mercure->publishOrderApproved($order);
        $this->realtime->publishOrderApproved($order);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeOrder(CustomerOrder $order): array
    {
        $data = $this->orderApiService->serializeOrder($order, true);
        $client = $order->getClient();
        $data['customerName'] = $client?->getUsername() ?? $client?->getEmail() ?? 'Customer';

        return $data;
    }
}
