<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Driver;
use App\Entity\Region;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * GET /api/drivers/search — query-parameter validation.
 *
 *   query : required, 2..100 chars   -> 422 otherwise
 *   limit : optional, 1..100         -> 422 otherwise
 *   valid -> 200 JSON array
 */
final class DriverSearchValidationTest extends WebTestCase
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
        $this->em->persist($region);
        foreach (['Иванов', 'Иваненко', 'Иванченко'] as $lastName) {
            $this->em->persist(
                (new Driver())->setLastName($lastName)->setFirstName('Иван')->setRegion($region),
            );
        }
        $this->em->flush();
        $this->em->clear();
    }

    private function get(string $queryString): int
    {
        $this->client->request('GET', '/api/drivers/search' . $queryString, server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        return $this->client->getResponse()->getStatusCode();
    }

    public function testValidQueryPasses(): void
    {
        self::assertSame(200, $this->get('?query=Ив'));
    }

    public function testMissingQueryIsRejected(): void
    {
        self::assertSame(422, $this->get(''));
    }

    public function testTooShortQueryIsRejected(): void
    {
        self::assertSame(422, $this->get('?query=И'));
    }

    public function testTooLongQueryIsRejected(): void
    {
        self::assertSame(422, $this->get('?query=' . str_repeat('и', 101)));
    }

    public function testZeroLimitIsRejected(): void
    {
        self::assertSame(422, $this->get('?query=Иван&limit=0'));
    }

    public function testTooLargeLimitIsRejected(): void
    {
        self::assertSame(422, $this->get('?query=Иван&limit=101'));
    }

    public function testLimitIsApplied(): void
    {
        $this->client->request('GET', '/api/drivers/search?query=Иван&limit=2', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(2, $data);
    }

    public function testValidationErrorBodyIsJson(): void
    {
        $this->client->request('GET', '/api/drivers/search?query=И', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJson((string) $this->client->getResponse()->getContent());
    }
}
