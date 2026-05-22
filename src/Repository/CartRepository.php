<?php

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function findOneByUser(User $user): ?Cart
    {
        return $this->createQueryBuilder('c')
            ->addSelect('i', 'p')
            ->leftJoin('c.items', 'i')
            ->leftJoin('i.product', 'p')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findCartIdForUser(User $user): ?int
    {
        $id = $this->createQueryBuilder('c')
            ->select('c.id')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        return $id !== null ? (int) $id : null;
    }

    public function touchCartById(int $cartId): void
    {
        $this->getEntityManager()
            ->createQuery('UPDATE App\Entity\Cart c SET c.updatedAt = :now WHERE c.id = :id')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('id', $cartId)
            ->execute();
    }
}
