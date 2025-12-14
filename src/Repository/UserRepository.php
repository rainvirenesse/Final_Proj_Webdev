<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return User[] Returns an array of client users (users with ROLE_USER only, not admin or staff)
     */
    public function findClientUsers(): array
    {
        $allUsers = $this->findAll();
        $clientUsers = [];
        
        foreach ($allUsers as $user) {
            $roles = $user->getRoles();
            // Only include users who have ROLE_USER but not ROLE_ADMIN or ROLE_STAFF
            if (in_array('ROLE_USER', $roles) && 
                !in_array('ROLE_ADMIN', $roles) && 
                !in_array('ROLE_STAFF', $roles) &&
                $user->getStatus() === User::STATUS_ACTIVE) {
                $clientUsers[] = $user;
            }
        }
        
        // Sort by email
        usort($clientUsers, function($a, $b) {
            return strcmp($a->getEmail(), $b->getEmail());
        });
        
        return $clientUsers;
    }

//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
