<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Driver;
use App\Entity\Region;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * /admin/drivers — list / create / edit / delete, behind ROLE_ADMIN.
 */
final class DriverCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

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
        $admin = (new User())->setEmail('admin@test.local')->setName('Admin')->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'x'));

        $region = (new Region())->setCode('UA-51')->setName('Odesa Oblast');
        $driver = (new Driver())->setLastName('Иванов')->setFirstName('Иван')->setRegion($region);

        $this->em->persist($admin);
        $this->em->persist($region);
        $this->em->persist($driver);
        $this->em->flush();
        $this->em->clear();

        $this->client->loginUser($this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']));
    }

    public function testIndexListsDrivers(): void
    {
        $this->client->request('GET', '/admin/drivers');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.list-group', 'Иванов');
    }

    public function testCreateDriverDefaultsToOdesaRegion(): void
    {
        // region is not submitted — the form keeps whatever the controller pre-selected (UA-51)
        $this->client->request('GET', '/admin/drivers/new');
        $this->client->submitForm('Create', [
            'driver[lastName]' => 'Петренко',
            'driver[firstName]' => 'Петро',
        ]);

        self::assertResponseRedirects('/admin/drivers');
        $created = $this->em->getRepository(Driver::class)->findOneBy(['lastName' => 'Петренко']);
        self::assertNotNull($created);
        self::assertSame('UA-51', $created->getRegion()->getCode());
    }

    public function testCreateDriverRejectsBlankLastName(): void
    {
        $this->client->request('GET', '/admin/drivers/new');
        $this->client->submitForm('Create', [
            'driver[lastName]' => '',
            'driver[firstName]' => 'Петро',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertNull($this->em->getRepository(Driver::class)->findOneBy(['firstName' => 'Петро']));
    }

    public function testEditDriver(): void
    {
        $id = $this->em->getRepository(Driver::class)->findOneBy(['lastName' => 'Иванов'])->getId();

        $this->client->request('GET', "/admin/drivers/{$id}/edit");
        $this->client->submitForm('Update', ['driver[middleName]' => 'Иванович']);

        self::assertResponseRedirects('/admin/drivers');
        $this->em->clear();
        self::assertSame('Иванович', $this->em->getRepository(Driver::class)->find($id)->getMiddleName());
    }

    public function testDeleteDriver(): void
    {
        $id = $this->em->getRepository(Driver::class)->findOneBy(['lastName' => 'Иванов'])->getId();

        $this->client->request('GET', '/admin/drivers');
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/admin/drivers');
        self::assertNull($this->em->getRepository(Driver::class)->find($id));
    }
}
