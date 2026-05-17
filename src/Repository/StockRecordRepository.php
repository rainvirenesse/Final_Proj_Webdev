<?php

namespace App\Repository;

use App\Entity\StockRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockRecord>
 */
class StockRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockRecord::class);
    }

    /**
     * @return StockRecord[]
     */
    public function findAllRecentFirst(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('p', 'cb', 'ub')
            ->leftJoin('s.product', 'p')
            ->leftJoin('s.createdBy', 'cb')
            ->leftJoin('s.updatedBy', 'ub')
            ->orderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
