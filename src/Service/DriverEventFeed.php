<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AbstractDriverEvent;
use App\Entity\Driver;
use App\Repository\BlacklistEntryRepository;
use App\Repository\DriverHistoryEntryRepository;

/**
 * Paginated, chronological feed of everything recorded about a driver —
 * blacklist entries and history entries merged into one timeline.
 *
 * Doctrine can't query both in one DQL statement: AbstractDriverEvent is a
 * Mapped Superclass, not an entity, so BlacklistEntry and DriverHistoryEntry
 * live in two separate tables with no shared identity to UNION over. For the
 * volume this bot deals with (a handful of events per driver), loading both
 * collections and merging in PHP is simpler than two DB round-trips.
 */
final class DriverEventFeed
{
    public function __construct(
        private readonly BlacklistEntryRepository $blacklistEntries,
        private readonly DriverHistoryEntryRepository $historyEntries,
    ) {
    }

    /**
     * @return array{items: list<AbstractDriverEvent>, hasMore: bool}
     */
    public function page(Driver $driver, int $offset, int $limit): array
    {
        $all = [
            ...$this->blacklistEntries->findBy(['driver' => $driver]),
            ...$this->historyEntries->findBy(['driver' => $driver]),
        ];

        usort($all, static fn (AbstractDriverEvent $a, AbstractDriverEvent $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return [
            'items' => array_slice($all, $offset, $limit),
            'hasMore' => \count($all) > $offset + $limit,
        ];
    }
}
