<?php

namespace App\Service\Api;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\CustomerOrderRepository;
use App\Service\Mercure\MercureOrderPublisher;
use App\Service\OrderNumberGenerator;
use App\Service\Realtime\WebSocketBroadcastService;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerOrderApiService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustomerOrderRepository $orderRepository,
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly MercureOrderPublisher $mercure,
        private readonly WebSocketBroadcastService $realtime,
    ) {
    }

    /**
     * @return CustomerOrder[]
     */
    public function listForUser(User $user): array
    {
        return $this->orderRepository->createQueryBuilder('o')
            ->addSelect('items', 'product')
            ->leftJoin('o.orderItems', 'items')
            ->leftJoin('items.product', 'product')
            ->andWhere('o.client = :client')
            ->setParameter('client', $user)
            ->orderBy('o.orderedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findForUser(User $user, int $orderId): ?CustomerOrder
    {
        return $this->orderRepository->createQueryBuilder('o')
            ->addSelect('items', 'product')
            ->leftJoin('o.orderItems', 'items')
            ->leftJoin('items.product', 'product')
            ->andWhere('o.id = :id')
            ->andWhere('o.client = :client')
            ->setParameter('id', $orderId)
            ->setParameter('client', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function createFromCart(User $user, ?string $notes = null): CustomerOrder
    {
        $cart = $this->cartRepository->findOneByUser($user);
        if (!$cart || $cart->getItems()->isEmpty()) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        $order = new CustomerOrder();
        $order->setClient($user);
        $order->setCreatedBy($user);
        $order->setOrderNumber($this->orderNumberGenerator->generate());
        $order->setStatus(CustomerOrder::STATUS_PENDING);
        $order->setPaymentStatus(CustomerOrder::PAYMENT_UNPAID);
        $order->setOrderedAt(new \DateTimeImmutable());
        if ($notes !== null && $notes !== '') {
            $order->setNotes($notes);
        }

        $total = 0.0;

        foreach ($cart->getItems()->toArray() as $cartItem) {
            $product = $cartItem->getProduct();
            if (!$product instanceof Product) {
                continue;
            }
            if ($product->getStatus() !== Product::STATUS_ACTIVE) {
                throw new \InvalidArgumentException(sprintf('Product "%s" is no longer available.', $product->getName()));
            }
            if ($product->getStock() < $cartItem->getQuantity()) {
                throw new \InvalidArgumentException(sprintf('Insufficient stock for "%s".', $product->getName()));
            }

            $line = new OrderItem();
            $line->setProduct($product);
            $line->setServiceName($product->getName());
            $line->setPrice($cartItem->getUnitPrice());
            $line->setQuantity($cartItem->getQuantity());
            $line->setStatus('PENDING');
            $order->addOrderItem($line);

            $total += $cartItem->getLineTotal();
            $product->setStock($product->getStock() - $cartItem->getQuantity());
        }

        $order->setTotalPrice(number_format($total, 2, '.', ''));

        $cartId = $cart->getId();
        $this->em->persist($order);
        $this->em->flush();

        $this->mercure->publishOrderCreated($order);
        $this->realtime->publishOrderCreated($order);

        if ($cartId !== null) {
            $this->cartItemRepository->deleteAllForCartId($cartId);
            $this->cartRepository->touchCartById($cartId);
        }

        $userId = $user->getId();
        if ($userId !== null) {
            $this->realtime->publishCartUpdated($userId);
            $this->realtime->publishOrdersUpdated($userId);
        }

        return $order;
    }

    public function updateNotes(User $user, int $orderId, ?string $notes): CustomerOrder
    {
        $order = $this->findForUser($user, $orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Order not found.');
        }
        if ($order->getStatus() === CustomerOrder::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Completed orders cannot be modified.');
        }

        $order->setNotes($notes);
        $this->em->flush();

        return $order;
    }

    public function cancel(User $user, int $orderId): CustomerOrder
    {
        $order = $this->findForUser($user, $orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Order not found.');
        }
        if ($order->getStatus() === CustomerOrder::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Completed orders cannot be cancelled.');
        }
        if ($order->getStatus() === CustomerOrder::STATUS_CANCELLED) {
            throw new \InvalidArgumentException('Order is already cancelled.');
        }

        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();
            if ($product) {
                $product->setStock($product->getStock() + (int) $item->getQuantity());
            }
        }

        $order->setStatus(CustomerOrder::STATUS_CANCELLED);
        $this->em->flush();

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeOrder(CustomerOrder $order, bool $detailed = true): array
    {
        $items = [];
        foreach ($order->getOrderItems() as $item) {
            $row = [
                'id' => $item->getId(),
                'name' => $item->getServiceName(),
                'quantity' => $item->getQuantity(),
                'price' => (float) $item->getPrice(),
                'lineTotal' => (float) $item->getPrice() * (int) $item->getQuantity(),
                'status' => $item->getStatus(),
            ];
            if ($item->getProduct()) {
                $row['productId'] = $item->getProduct()->getId();
            }
            $items[] = $row;
        }

        $data = [
            'id' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'status' => $order->getStatus(),
            'paymentStatus' => $order->getPaymentStatus(),
            'totalPrice' => (float) $order->getTotalPrice(),
            'orderedAt' => $order->getOrderedAt()?->format(\DateTimeInterface::ATOM),
            'itemCount' => \count($items),
        ];

        if ($detailed) {
            $data['notes'] = $order->getNotes();
            $data['completedAt'] = $order->getCompletedAt()?->format(\DateTimeInterface::ATOM);
            $data['staffApprovedAt'] = $order->getStaffApprovedAt()?->format(\DateTimeInterface::ATOM);
            $data['items'] = $items;
        }

        return $data;
    }
}
