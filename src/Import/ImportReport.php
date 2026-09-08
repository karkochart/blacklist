<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Mutable tally of what happened during an import run, returned to the command
 * so it can print a summary and decide the exit code.
 */
final class ImportReport
{
    public int $rowsScanned = 0;
    public int $driversCreated = 0;
    public int $blacklistEntriesCreated = 0;
    public int $skipped = 0;

    /** @var list<string> human-readable "row N: reason" lines */
    public array $errors = [];

    public function addError(int $rowNumber, string $reason): void
    {
        $this->errors[] = sprintf('row %d: %s', $rowNumber, $reason);
    }
}
