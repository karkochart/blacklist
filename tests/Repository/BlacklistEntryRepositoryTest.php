<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\BlacklistEntry;
use App\Entity\Driver;
use App\Repository\BlacklistEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Contract for BlacklistEntryRepository::search():
 *   - matches the driver's name/licence number, same multi-word rule as DriverRepository::search()
 *   - also matches the entry's own text and reportedBy
 *   - ordered newest first
 */
final class BlacklistEntryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BlacklistEntryRepository $repository;

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
        $this->repository = self::getContainer()->get(BlacklistEntryRepository::class);

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['blacklist_entry', 'driver_history_entry', 'driver'] as $table) {
            $connection->executeStatement("TRUNCATE TABLE {$table}");
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $ivanov = (new Driver())->setLastName('Иванов')->setFirstName('Иван')->setLicenseNumber('ABC123');
        $petrenko = (new Driver())->setLastName('Петренко')->setFirstName('Петро');
        $this->em->persist($ivanov);
        $this->em->persist($petrenko);

        $this->em->persist((new BlacklistEntry())->setDriver($ivanov)->setText('owes 5000')->setReportedBy('Денис'));
        $this->em->persist((new BlacklistEntry())->setDriver($petrenko)->setText('damaged bumper')->setReportedBy('Оля'));

        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @param list<BlacklistEntry> $entries
     *
     * @return list<string>
     */
    private static function texts(array $entries): array
    {
        return array_map(static fn (BlacklistEntry $e): string => $e->getText(), $entries);
    }

    public function testMatchesByDriverLastName(): void
    {
        self::assertSame(['owes 5000'], self::texts($this->repository->search('Иванов')));
    }

    public function testMatchesByLicenseNumber(): void
    {
        self::assertSame(['owes 5000'], self::texts($this->repository->search('ABC123')));
    }

    public function testMatchesByEntryText(): void
    {
        self::assertSame(['damaged bumper'], self::texts($this->repository->search('bumper')));
    }

    public function testMatchesByReportedBy(): void
    {
        self::assertSame(['owes 5000'], self::texts($this->repository->search('Денис')));
    }

    public function testUnknownTermReturnsNothing(): void
    {
        self::assertSame([], $this->repository->search('nonexistent'));
    }

    public function testBlankTermReturnsNothing(): void
    {
        self::assertSame([], $this->repository->search('   '));
    }

    public function testCountMatchingMirrorsSearch(): void
    {
        self::assertSame(2, $this->repository->countMatching('о'));
        self::assertSame(1, $this->repository->countMatching('Иванов'));
        self::assertSame(0, $this->repository->countMatching('nonexistent'));
    }
}
