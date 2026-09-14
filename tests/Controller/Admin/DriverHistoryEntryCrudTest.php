<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Driver;
use App\Entity\DriverHistoryEntry;
use App\Entity\Region;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DriverHistoryEntryCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $driverId;

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
        foreach (['blacklist_entry', 'driver_history_entry', 'driver', 'region', 'user'] as $table) {
            $connection->executeStatement("TRUNCATE TABLE {$table}");
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin = (new User())->setEmail('admin@test.local')->setName('Karen')->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'x'));

        $region = (new Region())->setCode('UA-51')->setName('Odesa Oblast');
        $driver = (new Driver())->setLastName('Иванов')->setFirstName('Иван')->setRegion($region);

        $this->em->persist($admin);
        $this->em->persist($region);
        $this->em->persist($driver);
        $this->em->flush();
        $this->driverId = $driver->getId();
        $this->em->clear();

        $this->client->loginUser($this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']));
    }

    public function testCreateEntrySetsReporterToCurrentUser(): void
    {
        $this->client->request('GET', '/admin/driver-history/new');
        $this->client->submitForm('Create', [
            'driver_history_entry[driver]' => $this->driverId,
            'driver_history_entry[text]' => 'warned once, no blacklist entry',
        ]);

        self::assertResponseRedirects('/admin/driver-history');

        $entry = $this->em->getRepository(DriverHistoryEntry::class)->findOneBy([]);
        self::assertNotNull($entry);
        self::assertSame('warned once, no blacklist entry', $entry->getText());
        self::assertSame('Karen', $entry->getReporter()?->getName());
        self::assertSame('Karen', $entry->getReporterName());
    }

    public function testCreateEntryRejectsBlankText(): void
    {
        $this->client->request('GET', '/admin/driver-history/new');
        $this->client->submitForm('Create', [
            'driver_history_entry[driver]' => $this->driverId,
            'driver_history_entry[text]' => '',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertCount(0, $this->em->getRepository(DriverHistoryEntry::class)->findAll());
    }

    public function testEditEntry(): void
    {
        $entry = (new DriverHistoryEntry())
            ->setDriver($this->em->getReference(Driver::class, $this->driverId))
            ->setText('old');
        $this->em->persist($entry);
        $this->em->flush();
        $id = $entry->getId();
        $this->em->clear();

        $this->client->request('GET', "/admin/driver-history/{$id}/edit");
        $this->client->submitForm('Update', ['driver_history_entry[text]' => 'good customer, no issues']);

        self::assertResponseRedirects('/admin/driver-history');
        $this->em->clear();
        self::assertSame(
            'good customer, no issues',
            $this->em->getRepository(DriverHistoryEntry::class)->find($id)->getText(),
        );
    }

    public function testDeleteEntry(): void
    {
        $entry = (new DriverHistoryEntry())
            ->setDriver($this->em->getReference(Driver::class, $this->driverId))
            ->setText('x');
        $this->em->persist($entry);
        $this->em->flush();
        $id = $entry->getId();

        $this->client->request('GET', '/admin/driver-history');
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/admin/driver-history');
        self::assertNull($this->em->getRepository(DriverHistoryEntry::class)->find($id));
    }

    public function testNonAdminCannotAccess(): void
    {
        $user = (new User())->setEmail('plain@test.local')->setName('Plain');
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'x'));
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/driver-history');

        self::assertResponseStatusCodeSame(403);
    }
}
