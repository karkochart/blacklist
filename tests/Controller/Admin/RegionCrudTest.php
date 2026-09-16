<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Region;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * /admin/regions — list / create / edit / delete, all behind ROLE_ADMIN.
 */
final class RegionCrudTest extends WebTestCase
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
        $this->em->persist($admin);
        $this->em->persist((new Region())->setCode('UA-51')->setName('Odesa Oblast'));
        $this->em->flush();
        $this->em->clear();

        $this->client->loginUser($this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']));
    }

    public function testIndexListsRegions(): void
    {
        $this->client->request('GET', '/admin/regions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.list-group', 'UA-51');
    }

    public function testCreateRegion(): void
    {
        $this->client->request('GET', '/admin/regions/new');
        $this->client->submitForm('Create', [
            'region[code]' => 'UA-32',
            'region[name]' => 'Kyiv Oblast',
        ]);

        self::assertResponseRedirects('/admin/regions');
        self::assertNotNull($this->em->getRepository(Region::class)->findOneByCode('UA-32'));
    }

    public function testCreateRegionRejectsBadCode(): void
    {
        $this->client->request('GET', '/admin/regions/new');
        $this->client->submitForm('Create', [
            'region[code]' => 'nonsense',
            'region[name]' => 'X',
        ]);

        self::assertResponseIsUnprocessable(); // 422, form re-rendered with errors
        self::assertNull($this->em->getRepository(Region::class)->findOneByCode('nonsense'));
    }

    public function testEditRegion(): void
    {
        $id = $this->em->getRepository(Region::class)->findOneByCode('UA-51')->getId();

        $this->client->request('GET', "/admin/regions/{$id}/edit");
        $this->client->submitForm('Update', ['region[name]' => 'Odessa Region']);

        self::assertResponseRedirects('/admin/regions');
        $this->em->clear();
        self::assertSame('Odessa Region', $this->em->getRepository(Region::class)->findOneByCode('UA-51')->getName());
    }

    public function testDeleteRegion(): void
    {
        $region = $this->em->getRepository(Region::class)->findOneByCode('UA-51');
        $id = $region->getId();

        $this->client->request('GET', '/admin/regions');
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/admin/regions');
        self::assertNull($this->em->getRepository(Region::class)->find($id));
    }

    public function testNonAdminCannotAccess(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = (new User())->setEmail('u@test.local')->setName('U')->setRoles([]);
        $user->setPassword($hasher->hashPassword($user, 'x'));
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/regions');

        self::assertResponseStatusCodeSame(403);
    }
}
