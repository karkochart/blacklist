<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

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
        $this->hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('TRUNCATE TABLE user');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $admin = (new User())->setEmail('admin@test.local')->setName('Admin')->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'x'));
        $this->em->persist($admin);
        $this->em->flush();
        $this->em->clear();

        $this->client->loginUser($this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']));
    }

    private function user(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function testCreateUserHashesPassword(): void
    {
        $this->client->request('GET', '/admin/users/new');
        $this->client->submitForm('Create', [
            'user[email]' => 'op@test.local',
            'user[name]' => 'Operator',
            'user[plainPassword]' => 'longenough1',
        ]);

        self::assertResponseRedirects('/admin/users');

        $created = $this->user('op@test.local');
        self::assertNotNull($created);
        self::assertNotSame('longenough1', $created->getPassword(), 'password must be hashed, not stored raw');
        self::assertTrue($this->hasher->isPasswordValid($created, 'longenough1'));
    }

    public function testCreateUserRejectsShortPassword(): void
    {
        $this->client->request('GET', '/admin/users/new');
        $this->client->submitForm('Create', [
            'user[email]' => 'x@test.local',
            'user[name]' => 'X',
            'user[plainPassword]' => 'short',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertNull($this->user('x@test.local'));
    }

    public function testCreateUserRejectsDuplicateEmail(): void
    {
        $this->client->request('GET', '/admin/users/new');
        $this->client->submitForm('Create', [
            'user[email]' => 'admin@test.local',
            'user[name]' => 'Dup',
            'user[plainPassword]' => 'longenough1',
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testEditWithoutPasswordKeepsIt(): void
    {
        $u = (new User())->setEmail('e@test.local')->setName('E')->setRoles([]);
        $u->setPassword($this->hasher->hashPassword($u, 'original8'));
        $this->em->persist($u);
        $this->em->flush();
        $hashBefore = $u->getPassword();
        $id = $u->getId();
        $this->em->clear();

        $this->client->request('GET', "/admin/users/{$id}/edit");
        $this->client->submitForm('Update', ['user[name]' => 'Edited', 'user[plainPassword]' => '']);

        self::assertResponseRedirects('/admin/users');
        $this->em->clear();
        $after = $this->em->getRepository(User::class)->find($id);
        self::assertSame('Edited', $after->getName());
        self::assertSame($hashBefore, $after->getPassword());
    }

    public function testCannotDeleteSelf(): void
    {
        $id = $this->user('admin@test.local')->getId();

        // the self-check runs before the CSRF check, so no token is needed here
        $this->client->request('POST', "/admin/users/{$id}");

        self::assertResponseRedirects('/admin/users');
        self::assertNotNull($this->em->getRepository(User::class)->find($id));
    }

    public function testDeleteOtherUser(): void
    {
        $u = (new User())->setEmail('d@test.local')->setName('D')->setRoles([]);
        $u->setPassword($this->hasher->hashPassword($u, 'longenough1'));
        $this->em->persist($u);
        $this->em->flush();
        $id = $u->getId();

        // only "d@test.local" has a Delete button (the admin's own row hides it)
        $this->client->request('GET', '/admin/users');
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/admin/users');
        self::assertNull($this->em->getRepository(User::class)->find($id));
    }
}
