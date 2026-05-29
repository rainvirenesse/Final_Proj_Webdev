<?php

namespace App\Service\Api;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Exception\ProductOutOfStockException;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Service\Realtime\WebSocketBroadcastService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cart mutations avoid bidirectional nulling and duplicate cascade remove paths
 * that can cause Doctrine UnitOfWork infinite scheduling loops on flush.
 */
final class CustomerCartService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly ProductRepository $productRepository,
        private readonly ProductSerializer $productSerializer,
        private readonly WebSocketBroadcastService $realtime,
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
            throw new \InvalidArgumentException(sprintf(
                'Product with id %d was not found. Call GET /api/products and use an id from the response.',
                $productId
            ));
        }

        $this->assertProductAvailableForCart($product, $quantity);

        $cart = $this->getOrCreateCart($user);

        $existing = $this->cartItemRepository->findOneByCartAndProduct($cart, $product);
        if ($existing instanceof CartItem) {
            $newQty = $existing->getQuantity() + $quantity;
            $this->assertProductAvailableForCart($product, $newQty);
            $existing->setQuantity($newQty);
            $cart->touch();
            $this->em->flush();
            $this->realtime->publishCartUpdated($user->getId() ?? 0);

            return $existing;
        }

        $item = new CartItem();
        $item->setCart($cart);
        $item->setProduct($product);
        $item->setQuantity($quantity);
        $item->setUnitPrice((string) $product->getPrice());
        $cart->addItem($item);
        $cart->touch();
        $this->em->persist($item);
        $this->em->flush();
        $this->realtime->publishCartUpdated($user->getId() ?? 0);

        return $item;
    }

    public function updateItemQuantity(User $user, int $cartItemId, int $quantity): CartItem
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        $item = $this->findOwnedItem($user, $cartItemId);
        $product = $item->getProduct();
        if (!$product) {
            throw new \InvalidArgumentException('Cart item not found.');
        }

        $this->assertProductAvailableForCart($product, $quantity);

        $item->setQuantity($quantity);
        $item->getCart()?->touch();
        $this->em->flush();
        $this->realtime->publishCartUpdated($user->getId() ?? 0);

        return $item;
    }

    public function removeItem(User $user, int $cartItemId): Cart
    {
        $item = $this->findOwnedItem($user, $cartItemId);
        $cart = $item->getCart();
        if (!$cart) {
            throw new \InvalidArgumentException('Cart item not found.');
        }

        $this->em->remove($item);
        $cart->touch();
        $this->em->flush();
        $this->realtime->publishCartUpdated($user->getId() ?? 0);

        $reloaded = $this->cartRepository->findOneByUser($user);

        return $reloaded ?? $cart;
    }

    public function clearCart(User $user): void
    {
        $cartId = $this->cartRepository->findCartIdForUser($user);
        if ($cartId === null) {
            return;
        }

        $this->cartItemRepository->deleteAllForCartId($cartId);
        $this->cartRepository->touchCartById($cartId);
        $this->realtime->publishCartUpdated($user->getId() ?? 0);
    }

    /**
     * Blocks cart mutations when inventory is zero or the product is not sellable.
     *
     * @throws ProductOutOfStockException
     */
    private function assertProductAvailableForCart(Product $product, int $requestedQuantity): void
    {
        if ($product->getStatus() === Product::STATUS_INACTIVE) {
            throw new \InvalidArgumentException('Product is not available for purchase.');
        }

        if ($product->getStock() <= 0 || $product->getStatus() === Product::STATUS_OUT_OF_STOCK) {
            throw new ProductOutOfStockException();
        }

        if ($product->getStock() < $requestedQuantity) {
            throw new \InvalidArgumentException(sprintf('Only %d unit(s) in stock.', $product->getStock()));
        }
    }

    private function findOwnedItem(User $user, int $cartItemId): CartItem
    {
        $item = $this->cartItemRepository->findOneForUser($user, $cartItemId);
        if (!$item instanceof CartItem) {
            throw new \InvalidArgumentException('Cart item not found.');
        }

        return $item;
    }
}
