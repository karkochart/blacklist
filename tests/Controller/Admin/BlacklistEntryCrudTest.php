<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\BlacklistEntry;
use App\Entity\Driver;
use App\Entity\Region;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BlacklistEntryCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $driverId;
    private int $otherDriverId;

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
        $other = (new Driver())->setLastName('Петренко')->setFirstName('Петро')->setRegion($region);

        $this->em->persist($admin);
        $this->em->persist($region);
        $this->em->persist($driver);
        $this->em->persist($other);
        $this->em->flush();
        $this->driverId = $driver->getId();
        $this->otherDriverId = $other->getId();
        $this->em->clear();

        $this->client->loginUser($this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']));
    }

    public function testSearchFiltersToMatchingDriverOnly(): void
    {
        $ivanovEntry = (new BlacklistEntry())
            ->setDriver($this->em->getReference(Driver::class, $this->driverId))
            ->setText('owes 5000');
        $petrenkoEntry = (new BlacklistEntry())
            ->setDriver($this->em->getReference(Driver::class, $this->otherDriverId))
            ->setText('damaged bumper');
        $this->em->persist($ivanovEntry);
        $this->em->persist($petrenkoEntry);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/blacklist', ['q' => 'Иванов']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.list-group', 'owes 5000');
        self::assertSelectorTextNotContains('.list-group', 'damaged bumper');
        self::assertSame('Иванов', $crawler->filter('input[name="q"]')->attr('value'));
    }

    public function testSearchWithNoMatchesShowsEmptyMessage(): void
    {
        $this->client->request('GET', '/admin/blacklist', ['q' => 'Ковальчук']);

        self::assertSelectorTextContains('.list-group', 'Нічого не знайдено.');
    }

    public function testCreateEntrySetsReporterToCurrentUser(): void
    {
        $this->client->request('GET', '/admin/blacklist/new');
        $this->client->submitForm('Create', [
            'blacklist_entry[driver]' => $this->driverId,
            'blacklist_entry[text]' => 'owes 5000',
        ]);

        self::assertResponseRedirects('/admin/blacklist');

        $entry = $this->em->getRepository(BlacklistEntry::class)->findOneBy([]);
        self::assertNotNull($entry);
        self::assertSame('owes 5000', $entry->getText());
        self::assertSame('Karen', $entry->getReporter()?->getName());
        self::assertSame('Karen', $entry->getReporterName());
    }

    public function testCreateEntryRejectsBlankReason(): void
    {
        $this->client->request('GET', '/admin/blacklist/new');
        $this->client->submitForm('Create', [
            'blacklist_entry[driver]' => $this->driverId,
            'blacklist_entry[text]' => '',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertCount(0, $this->em->getRepository(BlacklistEntry::class)->findAll());
    }

    public function testEditEntry(): void
    {
        $entry = (new BlacklistEntry())
            ->setDriver($this->em->getReference(Driver::class, $this->driverId))
            ->setText('old');
        $this->em->persist($entry);
        $this->em->flush();
        $id = $entry->getId();
        $this->em->clear();

        $this->client->request('GET', "/admin/blacklist/{$id}/edit");
        $this->client->submitForm('Update', ['blacklist_entry[text]' => 'updated reason']);

        self::assertResponseRedirects('/admin/blacklist');
        $this->em->clear();
        self::assertSame('updated reason', $this->em->getRepository(BlacklistEntry::class)->find($id)->getText());
    }

    public function testDeleteEntry(): void
    {
        $entry = (new BlacklistEntry())
            ->setDriver($this->em->getReference(Driver::class, $this->driverId))
            ->setText('x');
        $this->em->persist($entry);
        $this->em->flush();
        $id = $entry->getId();

        $this->client->request('GET', '/admin/blacklist');
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/admin/blacklist');
        self::assertNull($this->em->getRepository(BlacklistEntry::class)->find($id));
    }
}
