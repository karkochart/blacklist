<?php

declare(strict_types=1);

namespace App\Admin;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * "Show 25/50, then load more" pagination for the plain, unfiltered admin
 * listings (Drivers, Users, ...). Reads how many rows to show from the
 * request's "show" query param, growing by $step each time the admin clicks
 * the load-more link — no JavaScript, no page-number math in templates.
 */
final class LoadMorePaginator
{
    public const int DEFAULT_LIMIT = 25;
    public const int STEP = 25;

    /**
     * @param array<string, 'ASC'|'DESC'> $orderBy
     */
    public function paginate(EntityRepository $repository, Request $request, array $orderBy): LoadMorePage
    {
        $limit = $request->query->getInt('show', self::DEFAULT_LIMIT);
        if ($limit < self::DEFAULT_LIMIT) {
            $limit = self::DEFAULT_LIMIT;
        }

        $total = $repository->count([]);
        $items = $repository->findBy([], $orderBy, $limit);

        return new LoadMorePage($items, $total, $limit, self::STEP);
    }
}
