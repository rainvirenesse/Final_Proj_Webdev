<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\CustomerOrder;
use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;

/**
 * Returns pending orders for admin dashboard HTTP polling.
 */
final class StaffNotificationPollService
{
    public function __construct(
        private readonly CustomerOrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly ProductRepository $productRepository,
        private readonly ServiceRepository $serviceRepository,
    ) {
    }

    /**
     * @return array{
     *   serverTime: string,
     *   pending: list<array<string, mixed>>,
     *   stats?: array<string, mixed>
     * }
     */
    public function poll(?\DateTimeImmutable $since = null): array
    {
        $window = new \DateTimeImmutable('-48 hours');
        $sinceCutoff = $since?->modify('-3 seconds') ?? new \DateTimeImmutable('-5 minutes');

        $orders = $this->orderRepository->createQueryBuilder('o')
            ->addSelect('items', 'client', 'product')
            ->leftJoin('o.orderItems', 'items')
            ->leftJoin('items.product', 'product')
            ->leftJoin('o.client', 'client')
            ->andWhere('o.staffApprovedAt IS NULL')
            ->andWhere('o.status != :cancelled')
            ->andWhere('o.orderedAt >= :window')
            ->setParameter('cancelled', CustomerOrder::STATUS_CANCELLED)
            ->setParameter('window', $window)
            ->orderBy('o.orderedAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $pending = [];
        $newSince = [];
        foreach ($orders as $order) {
            if (!$order instanceof CustomerOrder) {
                continue;
            }
            $notification = $this->orderToNotification($order);
            $pending[] = $notification;
            if ($order->getOrderedAt() >= $sinceCutoff) {
                $newSince[] = $notification;
            }
        }

        return [
            'serverTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'pending' => $pending,
            'newSince' => $newSince,
            'stats' => $this->buildStats(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStats(): array
    {
        $allOrders = $this->orderRepository->findAll();
        $activeOrders = 0;
        $completedOrders = 0;
        foreach ($allOrders as $order) {
            if (\in_array($order->getStatus(), [CustomerOrder::STATUS_IN_PROGRESS, CustomerOrder::STATUS_PENDING], true)) {
                ++$activeOrders;
            }
            if ($order->getStatus() === CustomerOrder::STATUS_COMPLETED) {
                ++$completedOrders;
            }
        }

        $totalRevenue = 0.0;
        foreach ($this->orderRepository->findBy(['status' => CustomerOrder::STATUS_COMPLETED]) as $order) {
            $totalRevenue += (float) $order->getTotalPrice();
        }

        $totalStaff = 0;
        foreach ($this->userRepository->findAll() as $user) {
            if (\in_array('ROLE_STAFF', $user->getRoles(), true) || \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                ++$totalStaff;
            }
        }

        return [
            'totalUsers' => \count($this->userRepository->findAll()),
            'totalStaff' => $totalStaff,
            'totalOrders' => \count($allOrders),
            'activeOrders' => $activeOrders,
            'completedOrders' => $completedOrders,
            'totalRevenueFormatted' => '₱'.number_format($totalRevenue, 2, '.', ','),
            'totalProducts' => $this->productRepository->countProducts(),
            'activeProducts' => $this->productRepository->countActiveProducts(),
            'totalProductStock' => $this->productRepository->sumStock(),
            'totalServices' => \count($this->serviceRepository->findAll()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderToNotification(CustomerOrder $order): array
    {
        $client = $order->getClient();
        $items = [];
        foreach ($order->getOrderItems() as $item) {
            $name = $item->getServiceName();
            if ($name === null || $name === '') {
                $name = $item->getProduct()?->getName() ?? 'Product';
            }
            $items[] = [
                'name' => $name,
                'quantity' => (int) $item->getQuantity(),
                'price' => (float) $item->getPrice(),
            ];
        }

        $status = $order->getStatus();

        return [
            'type' => 'order.created',
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'customerName' => $client?->getUsername() ?? $client?->getEmail() ?? 'Customer',
            'items' => $items,
            'total' => (float) $order->getTotalPrice(),
            'totalPrice' => (float) $order->getTotalPrice(),
            'status' => $status,
            'orderedAt' => $order->getOrderedAt()?->format(\DateTimeInterface::ATOM),
            'canApprove' => !$order->isStaffApproved()
                && $status !== CustomerOrder::STATUS_COMPLETED
                && $status !== CustomerOrder::STATUS_CANCELLED,
        ];
    }
}
