<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * One "page" of a load-more listing: the rows to show right now, plus enough
 * to render a "show more" control without the template doing arithmetic.
 */
final class LoadMorePage
{
    /**
     * @param list<object> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $limit,
        public readonly int $step,
    ) {
    }

    public function hasMore(): bool
    {
        return \count($this->items) < $this->total;
    }

    public function nextLimit(): int
    {
        return $this->limit + $this->step;
    }

    public function remaining(): int
    {
        return $this->total - \count($this->items);
    }
}
