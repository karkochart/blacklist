<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Driver;
use App\Repository\DriverRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test: hits the real (test) database.
 *
 * Contract for DriverRepository::search():
 *   search(string $term): list<Driver>
 *   - case-insensitive substring match over lastName, firstName and licenseNumber
 *   - blank / whitespace-only term returns []
 *   - results ordered by lastName, then firstName
 */
final class DriverRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DriverRepository $repository;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(DriverRepository::class);

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['blacklist_entry', 'driver_history_entry', 'driver'] as $table) {
            $connection->executeStatement("TRUNCATE TABLE {$table}");
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        foreach (self::fixtures() as [$lastName, $firstName, $licenseNumber]) {
            $this->em->persist(
                (new Driver())
                    ->setLastName($lastName)
                    ->setFirstName($firstName)
                    ->setLicenseNumber($licenseNumber),
            );
        }
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @return list<array{string, string, string|null}>
     */
    private static function fixtures(): array
    {
        return [
            ['Абрамов', 'Олег', null],
            ['Абакумова', 'Ольга', null],
            ['Иванов', 'Иван', null],
            ['Петренко', 'Петро', null],
            ['Сидоров', 'Сергій', 'ABC123456'],
        ];
    }

    /**
     * @param list<Driver> $drivers
     *
     * @return list<string> "LastName FirstName"
     */
    private static function names(array $drivers): array
    {
        return array_map(
            static fn (Driver $d): string => $d->getLastName() . ' ' . $d->getFirstName(),
            $drivers,
        );
    }

    public function testFindsByExactLastName(): void
    {
        self::assertSame(['Иванов Иван'], self::names($this->repository->search('Иванов')));
    }

    public function testIsCaseInsensitiveForCyrillic(): void
    {
        self::assertSame(['Иванов Иван'], self::names($this->repository->search('иванов')));
    }

    public function testMatchesFirstNameToo(): void
    {
        self::assertSame(['Петренко Петро'], self::names($this->repository->search('петро')));
    }

    public function testPartialMatchIsOrderedByLastNameThenFirstName(): void
    {
        self::assertSame(
            ['Абакумова Ольга', 'Абрамов Олег', 'Иванов Иван', 'Сидоров Сергій'],
            self::names($this->repository->search('ов')),
        );
    }

    public function testMatchesFullNameRegardlessOfWordOrder(): void
    {
        self::assertSame(['Иванов Иван'], self::names($this->repository->search('Иванов Иван')));
        self::assertSame(['Иванов Иван'], self::names($this->repository->search('Иван Иванов')));
    }

    public function testFullNameSearchDoesNotMatchUnrelatedDrivers(): void
    {
        // "Иванов Петро" — surname matches Ivanov, but no driver has that first name
        self::assertSame([], $this->repository->search('Иванов Петро'));
    }

    public function testFindsByLicenseNumber(): void
    {
        self::assertSame(['Сидоров Сергій'], self::names($this->repository->search('abc123456')));
    }

    public function testUnknownTermReturnsNothing(): void
    {
        self::assertSame([], $this->repository->search('Ковальчук'));
    }

    public function testBlankTermReturnsNothing(): void
    {
        self::assertSame([], $this->repository->search('   '));
    }
}
