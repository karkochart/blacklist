<?php

declare(strict_types=1);

namespace App\Import;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Reads the blacklist worksheet and turns each valid row into a Driver
 * (deduplicated) plus a BlacklistEntry, writing to the database in batches.
 */
final class BlacklistImporter
{
    private const int BATCH_SIZE = 200;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DriverNameParser $nameParser,
    ) {
    }

    /**
     * @param string   $file    absolute path to the xlsx file
     * @param bool     $dryRun  when true, nothing is flushed to the database
     * @param int|null $limit   process at most this many data rows
     */
    public function import(string $file, bool $dryRun = false, ?int $limit = null): ImportReport
    {
        throw new \LogicException('Not implemented yet');
    }
}
