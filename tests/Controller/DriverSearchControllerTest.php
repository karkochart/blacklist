<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\BlacklistEntry;
use App\Entity\Driver;
use App\Entity\Region;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test for GET /api/drivers/search.
 *
 * Contract:
 *   GET /api/drivers/search?query=<term>
 *   - 200 application/json, body is a JSON array of matching drivers
 *   - each driver: id, lastName, firstName, middleName, birthDate, licenseNumber,
 *     region { code, name }, blacklistEntries [ { reportedBy, text, occurredAt, isActive } ]
 *   - missing / blank query -> 200 []
 *   - no match -> 200 []
 */
final class DriverSearchControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

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
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['blacklist_entry', 'driver_history_entry', 'driver', 'region'] as $table) {
            $connection->executeStatement("TRUNCATE TABLE {$table}");
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $region = (new Region())->setCode('UA-51')->setName('Odesa Oblast');

        $ivanov = (new Driver())
            ->setLastName('Иванов')
            ->setFirstName('Иван')
            ->setMiddleName('Иванович')
            ->setBirthDate(new \DateTimeImmutable('1985-05-23'))
            ->setRegion($region);

        $entry = (new BlacklistEntry())
            ->setDriver($ivanov)
            ->setReportedBy('Денис')
            ->setText('owes money')
            ->setOccurredAt(new \DateTimeImmutable('2022-08-20'));

        $petrenko = (new Driver())
            ->setLastName('Петренко')
            ->setFirstName('Петро')
            ->setRegion($region);

        $this->em->persist($region);
        $this->em->persist($ivanov);
        $this->em->persist($entry);
        $this->em->persist($petrenko);
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @return array<mixed>
     */
    private function search(string $queryString): array
    {
        $this->client->request('GET', '/api/drivers/search' . $queryString);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        return json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }

    public function testReturnsMatchingDriver(): void
    {
        $data = $this->search('?query=Иванов');

        self::assertCount(1, $data);
        self::assertSame('Иванов', $data[0]['lastName']);
        self::assertSame('Иван', $data[0]['firstName']);
        self::assertSame('Иванович', $data[0]['middleName']);
        self::assertArrayHasKey('birthDate', $data[0]);
        self::assertArrayHasKey('licenseNumber', $data[0]);
    }

    public function testEmbedsRegion(): void
    {
        $data = $this->search('?query=Иванов');

        self::assertSame('UA-51', $data[0]['region']['code']);
        self::assertSame('Odesa Oblast', $data[0]['region']['name']);
    }

    public function testEmbedsBlacklistEntries(): void
    {
        $data = $this->search('?query=Иванов');

        self::assertCount(1, $data[0]['blacklistEntries']);
        self::assertSame('Денис', $data[0]['blacklistEntries'][0]['reportedBy']);
        self::assertSame('owes money', $data[0]['blacklistEntries'][0]['text']);
        self::assertTrue($data[0]['blacklistEntries'][0]['isActive']);
        self::assertStringStartsWith('2022-08-20', $data[0]['blacklistEntries'][0]['occurredAt']);
    }

    public function testQueryIsCaseInsensitive(): void
    {
        self::assertCount(1, $this->search('?query=иванов'));
    }

    public function testDriverWithoutEntriesStillSerializes(): void
    {
        $data = $this->search('?query=Петренко');

        self::assertCount(1, $data);
        self::assertSame([], $data[0]['blacklistEntries']);
    }

    public function testMissingQueryReturnsEmptyArray(): void
    {
        self::assertSame([], $this->search(''));
    }

    public function testNoMatchReturnsEmptyArray(): void
    {
        self::assertSame([], $this->search('?query=Ковальчук'));
    }
}
