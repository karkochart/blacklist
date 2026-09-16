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
     * Latest expiry across all of this user's grants (active or already lapsed) —
     * the point a new grant should extend from, so stacking two grants doesn't
     * waste the time left on the current one.
     */
    public function latestExpiry(TelegramUser $user): ?\DateTimeImmutable
    {
        $latest = $this->findBy(['telegramUser' => $user], ['expiresAt' => 'DESC'], 1);

        return ($latest[0] ?? null)?->getExpiresAt();
    }
}
