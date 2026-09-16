<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\TelegramUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function hasActiveSubscription(TelegramUser $user): bool
    {
        return null !== $this->createQueryBuilder('s')
            ->andWhere('s.telegramUser = :user')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The most recent grant (active or already lapsed) — the point a new grant
     * should extend from, and the row a manual date correction applies to.
     */
    public function latest(TelegramUser $user): ?Subscription
    {
        $latest = $this->findBy(['telegramUser' => $user], ['expiresAt' => 'DESC'], 1);

        return $latest[0] ?? null;
    }

    public function latestExpiry(TelegramUser $user): ?\DateTimeImmutable
    {
        return $this->latest($user)?->getExpiresAt();
    }
}
