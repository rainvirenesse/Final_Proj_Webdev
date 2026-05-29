<?php

declare(strict_types=1);

namespace App\Service\Mercure;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercureOrderPublisher
{
    public const CUSTOMER_APPROVED_MESSAGE = 'Thank you for ordering in R A I N, your order will now process';

    public function __construct(
        private readonly HubInterface $hub,
        private readonly MercureTopicFactory $topics,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publishOrderCreated(CustomerOrder $order): void
    {
        $client = $order->getClient();
        $customerName = $client?->getUsername() ?? $client?->getEmail() ?? 'Customer';

        $items = [];
        $stockUpdates = [];
        foreach ($order->getOrderItems() as $item) {
            $items[] = $this->serializeItem($item);
            $product = $item->getProduct();
            if ($product instanceof Product) {
                $stockUpdates[] = [
                    'productId' => $product->getId(),
                    'name' => $product->getName(),
                    'stock' => $product->getStock(),
                ];
            }
        }

        $payload = [
            'type' => 'order.created',
            'event' => 'order.created',
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'customerName' => $customerName,
            'items' => $items,
            'total' => (float) $order->getTotalPrice(),
            'status' => $order->getStatus(),
            'stockUpdates' => $stockUpdates,
        ];

        $this->publish($this->topics->orders(), $payload);
    }

    public function publishOrderApproved(CustomerOrder $order): void
    {
        $orderId = (int) $order->getId();
        $payload = [
            'type' => 'order.approved',
            'status' => 'approved',
            'orderId' => $orderId,
            'orderNumber' => $order->getOrderNumber(),
            'message' => self::CUSTOMER_APPROVED_MESSAGE,
        ];

        $this->publish($this->topics->orderStatus($orderId), $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publish(string $topic, array $payload): void
    {
        try {
            $this->hub->publish(new Update(
                $topic,
                json_encode($payload, JSON_THROW_ON_ERROR),
                true,
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure publish failed: {message}', [
                'message' => $e->getMessage(),
                'topic' => $topic,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(OrderItem $item): array
    {
        $row = [
            'id' => $item->getId(),
            'name' => $item->getServiceName(),
            'quantity' => (int) $item->getQuantity(),
            'price' => (float) $item->getPrice(),
            'lineTotal' => (float) $item->getPrice() * (int) $item->getQuantity(),
            'status' => $item->getStatus(),
        ];
        if ($item->getProduct()) {
            $row['productId'] = $item->getProduct()->getId();
        }

        return $row;
    }
}
