<?php

namespace App\Repository;

use App\Entity\Driver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Driver>
 */
class DriverRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Driver::class);
    }

    /**
     * Case-insensitive substring search over last name, first name and licence number.
     *
     * Case-insensitivity relies on the MariaDB collation (utf8mb4_uca1400_ai_ci);
     * an ASCII-only LIKE (e.g. SQLite) would need a normalised column instead.
     *
     * @return list<Driver> ordered by lastName, then firstName
     */
    public function search(string $term, int $limit = 50): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        // treat %, _ and \ from user input as literals, not LIKE wildcards
        $pattern = '%' . addcslashes($term, '\\%_') . '%';

        return $this->createQueryBuilder('d')
            ->andWhere('(d.lastName LIKE :term OR d.firstName LIKE :term OR d.licenseNumber LIKE :term)')
            ->setParameter('term', $pattern)
            ->orderBy('d.lastName', 'ASC')
            ->addOrderBy('d.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Driver[] Returns an array of Driver objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Driver
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
