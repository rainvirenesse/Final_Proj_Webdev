<?php

namespace App\Service\Api;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerCartService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CartRepository $cartRepository,
        private readonly ProductRepository $productRepository,
        private readonly ProductSerializer $productSerializer,
    ) {
    }

    public function getOrCreateCart(User $user): Cart
    {
        $cart = $this->cartRepository->findOneByUser($user);
        if ($cart instanceof Cart) {
            return $cart;
        }

        $cart = new Cart();
        $cart->setUser($user);
        $this->em->persist($cart);
        $this->em->flush();

        return $cart;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCart(Cart $cart): array
    {
        $items = [];
        $subtotal = 0.0;

        foreach ($cart->getItems() as $item) {
            $product = $item->getProduct();
            if (!$product) {
                continue;
            }
            $lineTotal = $item->getLineTotal();
            $subtotal += $lineTotal;
            $items[] = [
                'id' => $item->getId(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => (float) $item->getUnitPrice(),
                'lineTotal' => $lineTotal,
                'product' => $this->productSerializer->toArray($product),
            ];
        }

        return [
            'id' => $cart->getId(),
            'updatedAt' => $cart->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'itemCount' => \count($items),
            'subtotal' => round($subtotal, 2),
            'items' => $items,
        ];
    }

    public function addItem(User $user, int $productId, int $quantity): CartItem
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        $product = $this->productRepository->find($productId);
        if (!$product instanceof Product) {
            throw new \InvalidArgumentException('Product not found.');
        }
        if ($product->getStatus() !== Product::STATUS_ACTIVE) {
            throw new \InvalidArgumentException('Product is not available for purchase.');
        }
        if ($product->getStock() < $quantity) {
            throw new \InvalidArgumentException(sprintf('Only %d unit(s) in stock.', $product->getStock()));
        }

        $cart = $this->getOrCreateCart($user);

        foreach ($cart->getItems() as $existing) {
            if ($existing->getProduct()?->getId() === $product->getId()) {
                $newQty = $existing->getQuantity() + $quantity;
                if ($product->getStock() < $newQty) {
                    throw new \InvalidArgumentException(sprintf('Only %d unit(s) in stock.', $product->getStock()));
                }
                $existing->setQuantity($newQty);
                $cart->touch();
                $this->em->flush();

                return $existing;
            }
        }

        $item = new CartItem();
        $item->setProduct($product);
        $item->setQuantity($quantity);
        $item->setUnitPrice((string) $product->getPrice());
        $cart->addItem($item);
        $cart->touch();
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    public function updateItemQuantity(User $user, int $cartItemId, int $quantity): CartItem
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        $item = $this->findOwnedItem($user, $cartItemId);
        $product = $item->getProduct();
        if (!$product || $product->getStock() < $quantity) {
            throw new \InvalidArgumentException('Insufficient stock for this quantity.');
        }

        $item->setQuantity($quantity);
        $item->getCart()?->touch();
        $this->em->flush();

        return $item;
    }

    public function removeItem(User $user, int $cartItemId): void
    {
        $item = $this->findOwnedItem($user, $cartItemId);
        $cart = $item->getCart();
        if ($cart) {
            $cart->removeItem($item);
            $cart->touch();
        }
        $this->em->remove($item);
        $this->em->flush();
    }

    public function clearCart(User $user): void
    {
        $cart = $this->cartRepository->findOneByUser($user);
        if (!$cart) {
            return;
        }
        foreach ($cart->getItems()->toArray() as $item) {
            $cart->removeItem($item);
            $this->em->remove($item);
        }
        $cart->touch();
        $this->em->flush();
    }

    private function findOwnedItem(User $user, int $cartItemId): CartItem
    {
        $cart = $this->getOrCreateCart($user);
        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $cartItemId) {
                return $item;
            }
        }

        throw new \InvalidArgumentException('Cart item not found.');
    }
}
