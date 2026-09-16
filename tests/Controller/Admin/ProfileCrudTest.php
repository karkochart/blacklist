<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProfileCrudTest extends WebTestCase
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

        $admin = (new User())->setEmail('admin@test.local')->setName('Karen')->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'old-password'));
        $this->em->persist($admin);
        $this->em->flush();
        $this->em->clear();

        $this->client->loginUser($this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']));
    }

    public function testProfileShowsCurrentUsersOwnData(): void
    {
        $crawler = $this->client->request('GET', '/admin/profile');

        self::assertResponseIsSuccessful();
        self::assertSame('admin@test.local', $crawler->filter('input[name="profile[email]"]')->attr('value'));
        self::assertSame('Karen', $crawler->filter('input[name="profile[name]"]')->attr('value'));
    }

    public function testUpdatingNameAndEmailWithoutTouchingPassword(): void
    {
        $this->client->request('GET', '/admin/profile');
        $this->client->submitForm('Save', [
            'profile[email]' => 'karen@test.local',
            'profile[name]' => 'Karen K.',
        ]);

        self::assertResponseRedirects('/admin/profile');

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'karen@test.local']);
        self::assertNotNull($user);
        self::assertSame('Karen K.', $user->getName());
        self::assertTrue($this->hasher->isPasswordValid($user, 'old-password'), 'password must be untouched');
    }

    public function testBlankPasswordFieldKeepsTheOldPassword(): void
    {
        $this->client->request('GET', '/admin/profile');
        $this->client->submitForm('Save', [
            'profile[email]' => 'admin@test.local',
            'profile[name]' => 'Karen',
            'profile[plainPassword]' => '',
        ]);

        self::assertResponseRedirects('/admin/profile');

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']);
        self::assertTrue($this->hasher->isPasswordValid($user, 'old-password'));
    }

    public function testSettingANewPasswordReplacesTheOldOne(): void
    {
        $this->client->request('GET', '/admin/profile');
        $this->client->submitForm('Save', [
            'profile[email]' => 'admin@test.local',
            'profile[name]' => 'Karen',
            'profile[plainPassword]' => 'brand-new-password',
        ]);

        self::assertResponseRedirects('/admin/profile');

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']);
        self::assertTrue($this->hasher->isPasswordValid($user, 'brand-new-password'));
        self::assertFalse($this->hasher->isPasswordValid($user, 'old-password'));
    }

    public function testTooShortPasswordIsRejected(): void
    {
        $this->client->request('GET', '/admin/profile');
        $this->client->submitForm('Save', [
            'profile[email]' => 'admin@test.local',
            'profile[name]' => 'Karen',
            'profile[plainPassword]' => 'short',
        ]);

        self::assertResponseIsUnprocessable();

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']);
        self::assertTrue($this->hasher->isPasswordValid($user, 'old-password'), 'rejected submission must not change the password');
    }

    public function testNonAdminCannotAccess(): void
    {
        $user = (new User())->setEmail('plain@test.local')->setName('Plain');
        $user->setPassword($this->hasher->hashPassword($user, 'x'));
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/profile');

        self::assertResponseStatusCodeSame(403);
    }
}
