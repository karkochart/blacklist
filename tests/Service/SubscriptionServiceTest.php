<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\TelegramUser;
use App\Enum\SubscriptionType;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SubscriptionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SubscriptionService $subscriptions;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->subscriptions = self::getContainer()->get(SubscriptionService::class);

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['subscription', 'telegram_user'] as $table) {
            $connection->executeStatement("TRUNCATE TABLE {$table}");
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testFreshUserHasNoActiveSubscription(): void
    {
        $user = new TelegramUser(1);
        $this->em->persist($user);
        $this->em->flush();

        self::assertFalse($this->subscriptions->isActive($user));
    }

    public function testGrantingADailySubscriptionMakesItActive(): void
    {
        $user = new TelegramUser(2);
        $this->em->persist($user);
        $this->em->flush();

        $subscription = $this->subscriptions->grant($user, SubscriptionType::DAILY);

        self::assertTrue($this->subscriptions->isActive($user));
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable('+1 day'))->getTimestamp(),
            $subscription->getExpiresAt()->getTimestamp(),
            5,
        );
    }

    public function testGrantingWhileAlreadySubscribedExtendsFromCurrentExpiryNotNow(): void
    {
        $user = new TelegramUser(3);
        $this->em->persist($user);
        $this->em->flush();

        $first = $this->subscriptions->grant($user, SubscriptionType::MONTHLY);
        $second = $this->subscriptions->grant($user, SubscriptionType::DAILY);

        // stacked: second starts where the first ends, not from "now" — paying
        // for another period early must not shorten time already owned
        self::assertEquals($first->getExpiresAt(), $second->getStartsAt());
        self::assertEqualsWithDelta(
            $first->getExpiresAt()->add(new \DateInterval('P1D'))->getTimestamp(),
            $second->getExpiresAt()->getTimestamp(),
            5,
        );
    }

    public function testExpiredSubscriptionIsNotActive(): void
    {
        $user = new TelegramUser(4);
        $this->em->persist($user);
        $this->em->flush();

        // grant() always starts from now-or-later, so build an already-lapsed one directly
        $this->em->persist(new \App\Entity\Subscription(
            $user,
            SubscriptionType::DAILY,
            new \DateTimeImmutable('-2 days'),
            new \DateTimeImmutable('-1 day'),
        ));
        $this->em->flush();

        self::assertFalse($this->subscriptions->isActive($user));
    }
}
