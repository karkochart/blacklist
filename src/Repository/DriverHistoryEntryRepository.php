<?php

namespace App\Repository;

use App\Entity\DriverHistoryEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DriverHistoryEntry>
 */
class DriverHistoryEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DriverHistoryEntry::class);
    }
}
