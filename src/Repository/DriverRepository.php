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
     * Case-insensitive substring search over last name, first name, middle name and licence number.
     *
     * A multi-word term (e.g. "Іванов Іван") is split on whitespace: every word must match
     * *somewhere* among the name/license fields (AND across words, OR across fields per word),
     * so the caller doesn't need to know whether the surname or given name comes first.
     *
     * Case-insensitivity relies on the MariaDB collation (utf8mb4_uca1400_ai_ci);
     * an ASCII-only LIKE (e.g. SQLite) would need a normalised column instead.
     *
     * @return list<Driver> ordered by lastName, then firstName
     */
    public function search(string $term, int $limit = 50): array
    {
        $words = preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.lastName', 'ASC')
            ->addOrderBy('d.firstName', 'ASC')
            ->setMaxResults($limit);

        foreach ($words as $i => $word) {
            // treat %, _ and \ from user input as literals, not LIKE wildcards
            $pattern = '%' . addcslashes($word, '\\%_') . '%';
            $qb->andWhere(sprintf(
                '(d.lastName LIKE :term%1$d OR d.firstName LIKE :term%1$d OR d.middleName LIKE :term%1$d OR d.licenseNumber LIKE :term%1$d)',
                $i,
            ))->setParameter("term{$i}", $pattern);
        }

        return $qb->getQuery()->getResult();
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
