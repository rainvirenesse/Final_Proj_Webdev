<?php

namespace App\DataFixtures;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Service;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OrderFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Get customers
        $admin = $manager->getRepository(User::class)->findOneBy(['email' => 'raincredo91@gmail.com']);
        $allUsers = $manager->getRepository(User::class)->findAll();
        $customers = array_values(array_filter($allUsers, function(User $u) {
            return in_array('ROLE_CUSTOMER', $u->getRoles());
        }));
        if (empty($customers)) {
            return; // CustomerFixtures must be loaded first
        }

        // Get services
        $services = $manager->getRepository(Service::class)->findAll();
        if (empty($services)) {
            return; // ServiceFixtures must be loaded first
        }

        $orders = [
            [
                'customer' => $customers[0] ?? null,
                'orderNumber' => 'ORD-2024-001',
                'status' => CustomerOrder::STATUS_COMPLETED,
                'paymentStatus' => CustomerOrder::PAYMENT_PAID,
                'orderedAt' => new \DateTimeImmutable('-30 days'),
                'completedAt' => new \DateTimeImmutable('-25 days'),
                'notes' => 'Customer requested express service',
                'items' => [
                    ['service' => $services[0] ?? null, 'quantity' => 1, 'price' => 850.00], // Custom Shoe Design
                ],
            ],
            [
                'customer' => $customers[1] ?? null,
                'orderNumber' => 'ORD-2024-002',
                'status' => CustomerOrder::STATUS_IN_PROGRESS,
                'paymentStatus' => CustomerOrder::PAYMENT_PARTIALLY_PAID,
                'orderedAt' => new \DateTimeImmutable('-15 days'),
                'completedAt' => null,
                'notes' => 'In progress - awaiting customer approval',
                'items' => [
                    ['service' => $services[1] ?? null, 'quantity' => 2, 'price' => 75.00], // Premium Shoe Repair
                    ['service' => $services[3] ?? null, 'quantity' => 2, 'price' => 45.00], // Leather Conditioning
                ],
            ],
            [
                'customer' => $customers[2] ?? null,
                'orderNumber' => 'ORD-2024-003',
                'status' => CustomerOrder::STATUS_PENDING,
                'paymentStatus' => CustomerOrder::PAYMENT_UNPAID,
                'orderedAt' => new \DateTimeImmutable('-5 days'),
                'completedAt' => null,
                'notes' => 'New order - pending payment',
                'items' => [
                    ['service' => $services[2] ?? null, 'quantity' => 1, 'price' => 120.00], // Shoe Resoling
                ],
            ],
            [
                'customer' => $customers[3] ?? null,
                'orderNumber' => 'ORD-2024-004',
                'status' => CustomerOrder::STATUS_COMPLETED,
                'paymentStatus' => CustomerOrder::PAYMENT_PAID,
                'orderedAt' => new \DateTimeImmutable('-20 days'),
                'completedAt' => new \DateTimeImmutable('-18 days'),
                'notes' => 'Satisfied customer - excellent service',
                'items' => [
                    ['service' => $services[8] ?? null, 'quantity' => 1, 'price' => 200.00], // Shoe Restoration Package
                ],
            ],
            [
                'customer' => $customers[0] ?? null,
                'orderNumber' => 'ORD-2024-005',
                'status' => CustomerOrder::STATUS_IN_PROGRESS,
                'paymentStatus' => CustomerOrder::PAYMENT_PAID,
                'orderedAt' => new \DateTimeImmutable('-10 days'),
                'completedAt' => null,
                'notes' => 'Repeat customer - high priority',
                'items' => [
                    ['service' => $services[6] ?? null, 'quantity' => 1, 'price' => 150.00], // Orthotic Insole
                ],
            ],
            [
                'customer' => $customers[4] ?? null,
                'orderNumber' => 'ORD-2024-006',
                'status' => CustomerOrder::STATUS_PENDING,
                'paymentStatus' => CustomerOrder::PAYMENT_UNPAID,
                'orderedAt' => new \DateTimeImmutable('-3 days'),
                'completedAt' => null,
                'notes' => null,
                'items' => [
                    ['service' => $services[4] ?? null, 'quantity' => 1, 'price' => 35.00], // Shoe Stretching
                    ['service' => $services[7] ?? null, 'quantity' => 3, 'price' => 25.00], // Express Shine
                ],
            ],
            [
                'customer' => $customers[5] ?? null,
                'orderNumber' => 'ORD-2024-007',
                'status' => CustomerOrder::STATUS_COMPLETED,
                'paymentStatus' => CustomerOrder::PAYMENT_PAID,
                'orderedAt' => new \DateTimeImmutable('-12 days'),
                'completedAt' => new \DateTimeImmutable('-8 days'),
                'notes' => 'Color matching service completed',
                'items' => [
                    ['service' => $services[5] ?? null, 'quantity' => 1, 'price' => 95.00], // Color Matching
                ],
            ],
            [
                'customer' => $customers[6] ?? null,
                'orderNumber' => 'ORD-2024-008',
                'status' => CustomerOrder::STATUS_IN_PROGRESS,
                'paymentStatus' => CustomerOrder::PAYMENT_PAID,
                'orderedAt' => new \DateTimeImmutable('-7 days'),
                'completedAt' => null,
                'notes' => 'Bespoke consultation scheduled',
                'items' => [
                    ['service' => $services[9] ?? null, 'quantity' => 1, 'price' => 150.00], // Bespoke Consultation
                ],
            ],
            [
                'customer' => $customers[2] ?? null,
                'orderNumber' => 'ORD-2024-009',
                'status' => CustomerOrder::STATUS_COMPLETED,
                'paymentStatus' => CustomerOrder::PAYMENT_PAID,
                'orderedAt' => new \DateTimeImmutable('-40 days'),
                'completedAt' => new \DateTimeImmutable('-35 days'),
                'notes' => 'Multiple pairs restored',
                'items' => [
                    ['service' => $services[1] ?? null, 'quantity' => 3, 'price' => 75.00], // Premium Repair
                    ['service' => $services[3] ?? null, 'quantity' => 3, 'price' => 45.00], // Leather Care
                ],
            ],
            [
                'customer' => $customers[7] ?? null,
                'orderNumber' => 'ORD-2024-010',
                'status' => CustomerOrder::STATUS_PENDING,
                'paymentStatus' => CustomerOrder::PAYMENT_UNPAID,
                'orderedAt' => new \DateTimeImmutable('-1 day'),
                'completedAt' => null,
                'notes' => 'New customer - first order',
                'items' => [
                    ['service' => $services[0] ?? null, 'quantity' => 1, 'price' => 850.00], // Custom Design
                ],
            ],
        ];

        foreach ($orders as $orderData) {
            if (!$orderData['customer']) {
                continue;
            }

            $order = new CustomerOrder();
            $order->setClient($orderData['customer']);
            $order->setOrderNumber($orderData['orderNumber']);
            $order->setStatus($orderData['status']);
            $order->setPaymentStatus($orderData['paymentStatus']);
            $order->setOrderedAt($orderData['orderedAt']);
            $order->setCompletedAt($orderData['completedAt']);
            $order->setNotes($orderData['notes']);

            $totalPrice = 0.00;

            // Add order items
            foreach ($orderData['items'] as $itemData) {
                if (!$itemData['service']) {
                    continue;
                }

                $orderItem = new OrderItem();
                $orderItem->setOrder($order);
                $orderItem->setService($itemData['service']);
                $orderItem->setServiceName($itemData['service']->getName());
                $orderItem->setPrice((string) $itemData['price']);
                $orderItem->setQuantity($itemData['quantity']);
                $orderItem->setRevisions($itemData['service']->getRevisions());
                $orderItem->setStatus($orderData['status'] === CustomerOrder::STATUS_COMPLETED ? 'COMPLETED' : ($orderData['status'] === CustomerOrder::STATUS_IN_PROGRESS ? 'IN_PROGRESS' : 'PENDING'));
                $orderItem->setCreatedAt($orderData['orderedAt']);
                $orderItem->setUpdatedAt($orderData['orderedAt']);

                if ($orderData['status'] === CustomerOrder::STATUS_COMPLETED && $orderData['completedAt']) {
                    $orderItem->setDeliveryDate($orderData['completedAt']);
                }

                $order->addOrderItem($orderItem);
                $totalPrice += $itemData['price'] * $itemData['quantity'];
            }

            $order->setTotalPrice((string) $totalPrice);
            $manager->persist($order);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CustomerFixtures::class,
            ServiceFixtures::class,
        ];
    }
}