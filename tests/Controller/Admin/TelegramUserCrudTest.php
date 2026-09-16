<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Subscription;
use App\Entity\TelegramUser;
use App\Entity\User;
use App\Enum\SubscriptionType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TelegramUserCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $telegramUserId;

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
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['subscription', 'telegram_user', 'user'] as $table) {
            $connection->executeStatement("TRUNCATE TABLE {$table}");
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin = (new User())->setEmail('admin@test.local')->setName('Karen')->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'x'));

        $telegramUser = new TelegramUser(4242);
        $telegramUser->setUsername('renter42');

        $this->em->persist($admin);
        $this->em->persist($telegramUser);
        $this->em->flush();
        $this->telegramUserId = $telegramUser->getId();
        $this->em->clear();

        $this->client->loginUser($this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']));
    }

    public function testGrantMonthlySubscription(): void
    {
        $this->client->request('GET', '/admin/telegram-users');
        $this->client->submitForm('Grant', ['type' => 'monthly']);

        self::assertResponseRedirects('/admin/telegram-users');

        $telegramUser = $this->em->getRepository(TelegramUser::class)->find($this->telegramUserId);
        $subscriptions = $this->em->getRepository(Subscription::class)->findBy(['telegramUser' => $telegramUser]);

        self::assertCount(1, $subscriptions);
        self::assertSame(SubscriptionType::MONTHLY, $subscriptions[0]->getType());
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable('+1 month'))->getTimestamp(),
            $subscriptions[0]->getExpiresAt()->getTimestamp(),
            5,
        );
    }

    public function testGrantRecordsWhichAdminGrantedIt(): void
    {
        $this->client->request('GET', '/admin/telegram-users');
        $this->client->submitForm('Grant', ['type' => 'daily']);

        $subscription = $this->em->getRepository(Subscription::class)->findOneBy([]);
        self::assertSame('admin@test.local', $subscription->getGrantedBy()?->getEmail());
    }

    public function testNonAdminCannotAccess(): void
    {
        $user = (new User())->setEmail('plain@test.local')->setName('Plain');
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'x'));
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/telegram-users');

        self::assertResponseStatusCodeSame(403);
    }
}
