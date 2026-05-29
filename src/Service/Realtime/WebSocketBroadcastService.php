<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Entity\ActivityLog;
use App\Entity\CustomerOrder;
use App\Entity\Payment;
use App\Entity\Product;
use App\Service\Mercure\MercureOrderPublisher;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Publishes JSON events to the CREDO WebSocket hub (realtime-ws/server.mjs).
 */
final class WebSocketBroadcastService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $broadcastUrl = null,
        private readonly ?string $broadcastKey = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $channels
     */
    public function publish(string $type, array $payload = [], array $channels = ['staff']): void
    {
        $url = $this->broadcastUrl ?? '';
        if ($url === '') {
            return;
        }

        $body = json_encode([
            'type' => $type,
            'payload' => $payload,
            'channels' => $channels,
            'sentAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        try {
            $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Broadcast-Key' => $this->broadcastKey ?? '',
                ],
                'body' => $body,
                'timeout' => 2,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('WebSocket broadcast failed: {message}', [
                'message' => $e->getMessage(),
                'type' => $type,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publishToStaff(string $type, array $payload = []): void
    {
        $this->publish($type, $payload, ['staff']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publishToUser(int $userId, string $type, array $payload = []): void
    {
        $this->publish($type, $payload, ['user:'.$userId]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publishToStaffAndUser(int $userId, string $type, array $payload = []): void
    {
        $this->publish($type, $payload, ['staff', 'user:'.$userId]);
    }

    public function publishCartUpdated(int $userId): void
    {
        $this->publishToUser($userId, 'cart.updated', ['userId' => $userId]);
    }

    public function publishProductsUpdated(): void
    {
        $this->publish('products.updated', [], ['catalog', 'staff']);
    }

    public function publishOrdersUpdated(int $userId): void
    {
        $this->publishToUser($userId, 'orders.updated', ['userId' => $userId]);
    }

    public function publishOrderCreated(CustomerOrder $order): void
    {
        $clientId = $order->getClient()?->getId();
        $payload = $this->orderPayload($order);
        if ($clientId !== null) {
            $this->publishToStaffAndUser($clientId, 'order.created', $payload);
        } else {
            $this->publishToStaff('order.created', $payload);
        }
    }

    public function publishOrderStatusChanged(CustomerOrder $order, ?string $previousStatus = null): void
    {
        $clientId = $order->getClient()?->getId();
        $payload = $this->orderPayload($order, ['previousStatus' => $previousStatus]);
        if ($clientId !== null) {
            $this->publishToStaffAndUser($clientId, 'order.status_changed', $payload);
            $this->publishToUser($clientId, 'orders.updated', ['userId' => $clientId]);
        } else {
            $this->publishToStaff('order.status_changed', $payload);
        }
    }

    public function publishOrderApproved(CustomerOrder $order): void
    {
        $clientId = $order->getClient()?->getId();
        $message = MercureOrderPublisher::CUSTOMER_APPROVED_MESSAGE;
        $payload = $this->orderPayload($order, [
            'message' => $message,
            'title' => 'R A I N',
        ]);

        $this->publishToStaff('order.status_changed', $payload);

        if ($clientId !== null) {
            $this->publishToUser($clientId, 'order.approved', $payload);
            $this->publishToUser($clientId, 'orders.updated', ['userId' => $clientId]);
        }
    }

    public function publishPaymentCompleted(CustomerOrder $order, ?Payment $payment = null): void
    {
        $clientId = $order->getClient()?->getId();
        $payload = $this->orderPayload($order, [
            'payment' => $payment ? [
                'id' => $payment->getId(),
                'method' => $payment->getMethod(),
                'transactionReference' => $payment->getTransactionReference(),
                'amount' => $payment->getAmount(),
            ] : null,
        ]);
        if ($clientId !== null) {
            $this->publishToStaffAndUser($clientId, 'payment.completed', $payload);
            $this->publishToUser($clientId, 'orders.updated', ['userId' => $clientId]);
        } else {
            $this->publishToStaff('payment.completed', $payload);
        }
    }

    public function publishStockUpdated(Product $product, ?int $previousStock = null): void
    {
        $this->publish('stock.updated', [
            'productId' => $product->getId(),
            'name' => $product->getName(),
            'stock' => $product->getStock(),
            'previousStock' => $previousStock,
        ], ['staff', 'catalog']);
        $this->publishProductsUpdated();
    }

    public function publishActivityLogged(ActivityLog $log): void
    {
        $this->publishToStaff('activity.logged', [
            'id' => $log->getId(),
            'action' => $log->getAction(),
            'entityType' => $log->getEntityType(),
            'entityId' => $log->getEntityId(),
            'description' => $log->getDescription(),
            'userId' => $log->getUser()?->getId(),
            'userEmail' => $log->getUser()?->getEmail(),
            'createdAt' => $log->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function orderPayload(CustomerOrder $order, array $extra = []): array
    {
        $client = $order->getClient();

        return array_merge([
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'status' => $order->getStatus(),
            'paymentStatus' => $order->getPaymentStatus(),
            'totalPrice' => (float) ($order->getTotalPrice() ?? '0'),
            'clientId' => $client?->getId(),
            'customerName' => $client?->getUsername() ?? $client?->getEmail() ?? 'Customer',
        ], $extra);
    }
}
