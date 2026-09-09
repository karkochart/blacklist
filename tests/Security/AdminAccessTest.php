<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The /admin area is behind a session form-login and requires ROLE_ADMIN.
 *
 *   anonymous           -> 302 to /login
 *   ROLE_USER           -> 403
 *   ROLE_ADMIN          -> 200
 *   wrong credentials   -> back to /login
 *   right credentials   -> reach /admin
 */
final class AdminAccessTest extends WebTestCase
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
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE user');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = (new User())->setEmail('admin@test.local')->setName('Admin')->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'secret123'));

        $user = (new User())->setEmail('user@test.local')->setName('User')->setRoles([]);
        $user->setPassword($hasher->hashPassword($user, 'secret123'));

        $this->em->persist($admin);
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();
    }

    private function findUser(string $email): User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testLoginPageIsPublic(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="_password"]');
    }

    public function testNonAdminIsForbidden(): void
    {
        $this->client->loginUser($this->findUser('user@test.local'));
        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminReachesDashboard(): void
    {
        $this->client->loginUser($this->findUser('admin@test.local'));
        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Dashboard');
    }

    public function testWrongCredentialsStayOnLogin(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', [
            '_username' => 'admin@test.local',
            '_password' => 'wrong',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.error');
    }

    public function testRightCredentialsReachAdmin(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', [
            '_username' => 'admin@test.local',
            '_password' => 'secret123',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }
}
