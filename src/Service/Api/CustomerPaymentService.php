<?php

namespace App\Service\Api;

use App\Entity\CustomerOrder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerPaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustomerOrderApiService $orderApiService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentStatus(User $user, int $orderId): array
    {
        $order = $this->orderApiService->findForUser($user, $orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Order not found.');
        }

        return $this->serializePayment($order);
    }

    /**
     * @param array{method?: string, reference?: string} $payload
     *
     * @return array<string, mixed>
     */
    public function processPayment(User $user, int $orderId, array $payload): array
    {
        $order = $this->orderApiService->findForUser($user, $orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Order not found.');
        }
        if ($order->getStatus() === CustomerOrder::STATUS_CANCELLED) {
            throw new \InvalidArgumentException('Cannot pay for a cancelled order.');
        }
        if ($order->getPaymentStatus() === CustomerOrder::PAYMENT_PAID) {
            throw new \InvalidArgumentException('Order is already paid.');
        }

        $method = trim((string) ($payload['method'] ?? 'mobile'));
        if ($method === '') {
            throw new \InvalidArgumentException('Payment method is required.');
        }

        $reference = trim((string) ($payload['reference'] ?? ''));
        if ($reference === '') {
            $reference = 'PAY-'.strtoupper(bin2hex(random_bytes(4)));
        }

        $order->setPaymentStatus(CustomerOrder::PAYMENT_PAID);
        $note = trim((string) ($order->getNotes() ?? ''));
        $paymentNote = sprintf('[Paid via %s, ref: %s]', $method, $reference);
        $order->setNotes($note === '' ? $paymentNote : $note."\n".$paymentNote);

        $this->em->flush();

        return $this->serializePayment($order, $method, $reference);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(CustomerOrder $order, ?string $method = null, ?string $reference = null): array
    {
        return [
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'paymentStatus' => $order->getPaymentStatus(),
            'totalPrice' => (float) $order->getTotalPrice(),
            'orderStatus' => $order->getStatus(),
            'method' => $method,
            'reference' => $reference,
            'paid' => $order->getPaymentStatus() === CustomerOrder::PAYMENT_PAID,
        ];
    }
}
