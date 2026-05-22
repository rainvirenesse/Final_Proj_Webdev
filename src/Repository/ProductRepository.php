<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[] Returns an array of active Product objects
     */
    public function findActiveProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', Product::STATUS_ACTIVE)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Customer-facing catalog: active and out-of-stock items (excludes inactive/hidden).
     *
     * @return Product[]
     */
    public function findCatalogProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('statuses', [Product::STATUS_ACTIVE, Product::STATUS_OUT_OF_STOCK])
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int Returns the count of products
     */
    public function countProducts(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return int Returns the count of active products
     */
    public function countActiveProducts(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', Product::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumStock(): int
    {
        $sum = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.stock), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $sum;
    }
}

