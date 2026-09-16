<?php

namespace App\Repository;

use App\Entity\Driver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
        if (null === $qb = $this->matchingQueryBuilder($term)) {
            return [];
        }

        return $qb
            ->orderBy('d.lastName', 'ASC')
            ->addOrderBy('d.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Same matching rules as search(), just a count — used to know whether a
     * "show more" link is worth showing on an admin search results page.
     */
    public function countMatching(string $term): int
    {
        if (null === $qb = $this->matchingQueryBuilder($term)) {
            return 0;
        }

        return (int) $qb->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
    }

    private function matchingQueryBuilder(string $term): ?QueryBuilder
    {
        $words = preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === []) {
            return null;
        }

        $qb = $this->createQueryBuilder('d');

        foreach ($words as $i => $word) {
            // treat %, _ and \ from user input as literals, not LIKE wildcards
            $pattern = '%' . addcslashes($word, '\\%_') . '%';
            $qb->andWhere(sprintf(
                '(d.lastName LIKE :term%1$d OR d.firstName LIKE :term%1$d OR d.middleName LIKE :term%1$d OR d.licenseNumber LIKE :term%1$d)',
                $i,
            ))->setParameter("term{$i}", $pattern);
        }

        return $qb;
    }
}
