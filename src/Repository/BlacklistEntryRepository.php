<?php

namespace App\Repository;

use App\Entity\BlacklistEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class BlacklistEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlacklistEntry::class);
    }

    /**
     * Case-insensitive substring search over the driver's name/licence number
     * and the entry's own text/reportedBy — same multi-word AND-across-words,
     * OR-across-fields rule as DriverRepository::search().
     *
     * @return list<BlacklistEntry> ordered newest first
     */
    public function search(string $term, int $limit = 50): array
    {
        if (null === $qb = $this->matchingQueryBuilder($term)) {
            return [];
        }

        return $qb
            ->orderBy('b.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countMatching(string $term): int
    {
        if (null === $qb = $this->matchingQueryBuilder($term)) {
            return 0;
        }

        return (int) $qb->select('COUNT(b.id)')->getQuery()->getSingleScalarResult();
    }

    private function matchingQueryBuilder(string $term): ?QueryBuilder
    {
        $words = preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === []) {
            return null;
        }

        $qb = $this->createQueryBuilder('b')->join('b.driver', 'd');

        foreach ($words as $i => $word) {
            $pattern = '%' . addcslashes($word, '\\%_') . '%';
            $qb->andWhere(sprintf(
                '(d.lastName LIKE :term%1$d OR d.firstName LIKE :term%1$d OR d.middleName LIKE :term%1$d '
                . 'OR d.licenseNumber LIKE :term%1$d OR b.text LIKE :term%1$d OR b.reportedBy LIKE :term%1$d)',
                $i,
            ))->setParameter("term{$i}", $pattern);
        }

        return $qb;
    }
}
