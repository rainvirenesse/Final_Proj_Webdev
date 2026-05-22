<?php

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    public function findOneForUser(User $user, int $cartItemId): ?CartItem
    {
        return $this->createQueryBuilder('i')
            ->innerJoin('i.cart', 'c')
            ->addSelect('c', 'p')
            ->leftJoin('i.product', 'p')
            ->andWhere('i.id = :id')
            ->andWhere('c.user = :user')
            ->setParameter('id', $cartItemId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByCartAndProduct(Cart $cart, Product $product): ?CartItem
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.cart = :cart')
            ->andWhere('i.product = :product')
            ->setParameter('cart', $cart)
            ->setParameter('product', $product)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteAllForCartId(int $cartId): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\CartItem i WHERE i.cart = :cartId')
            ->setParameter('cartId', $cartId)
            ->execute();
    }
}
