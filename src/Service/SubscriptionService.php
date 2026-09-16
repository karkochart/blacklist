<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\TelegramUser;
use App\Entity\User;
use App\Enum\SubscriptionType;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SubscriptionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SubscriptionRepository $subscriptions,
    ) {
    }

    public function isActive(TelegramUser $user): bool
    {
        return $this->subscriptions->hasActiveSubscription($user);
    }

    /**
     * Grants a new period, stacked on top of whatever the user already has left
     * (if their current subscription hasn't expired yet, this extends it instead
     * of restarting from now — paying early shouldn't shorten what you already own).
     */
    public function grant(TelegramUser $user, SubscriptionType $type, ?User $grantedBy = null): Subscription
    {
        $now = new \DateTimeImmutable();
        $currentExpiry = $this->subscriptions->latestExpiry($user);
        $start = ($currentExpiry !== null && $currentExpiry > $now) ? $currentExpiry : $now;

        $subscription = new Subscription($user, $type, $start, $start->add($type->duration()), $grantedBy);
        $this->em->persist($subscription);
        $this->em->flush();

        return $subscription;
    }
}
