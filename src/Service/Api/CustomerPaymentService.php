<?php

namespace App\Service\Api;

use App\Entity\CustomerOrder;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Service\Realtime\WebSocketBroadcastService;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerPaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustomerOrderApiService $orderApiService,
        private readonly PaymentRepository $paymentRepository,
        private readonly WebSocketBroadcastService $realtime,
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
     * @param array{method?: string, reference?: string, orderId?: int} $payload
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

        $payment = new Payment();
        $payment->setOrder($order);
        $payment->setMethod($method);
        $payment->setTransactionReference($reference);
        $payment->setAmount($order->getTotalPrice() ?? '0.00');
        $payment->setStatus(Payment::STATUS_COMPLETED);
        $order->addPayment($payment);
        $order->setPaymentStatus(CustomerOrder::PAYMENT_PAID);
        // Pending + paid is invalid; payment advances the order to In Progress.
        if ($order->getStatus() === CustomerOrder::STATUS_PENDING) {
            $order->setStatus(CustomerOrder::STATUS_IN_PROGRESS);
        }

        $note = trim((string) ($order->getNotes() ?? ''));
        $paymentNote = sprintf('[Paid via %s, ref: %s]', $method, $reference);
        $order->setNotes($note === '' ? $paymentNote : $note."\n".$paymentNote);

        $this->em->persist($payment);
        $this->em->flush();

        // Ensure staff dashboards get an immediate payment event, even if doctrine listeners
        // are disabled/misconfigured in a given environment.
        $this->realtime->publishPaymentCompleted($order, $payment);

        return $this->serializePayment($order, $payment);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(CustomerOrder $order, ?Payment $payment = null): array
    {
        $payment ??= $this->paymentRepository->findLatestForOrder($order);

        return [
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'paymentStatus' => $order->getPaymentStatus(),
            'totalPrice' => (float) $order->getTotalPrice(),
            'orderStatus' => $order->getStatus(),
            'paid' => $order->getPaymentStatus() === CustomerOrder::PAYMENT_PAID,
            'payment' => $payment ? [
                'id' => $payment->getId(),
                'transactionReference' => $payment->getTransactionReference(),
                'status' => $payment->getStatus(),
                'method' => $payment->getMethod(),
                'amount' => (float) $payment->getAmount(),
                'createdAt' => $payment->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ] : null,
        ];
    }
}
