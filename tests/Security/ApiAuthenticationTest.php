<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Driver;
use App\Entity\Region;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The /api firewall requires a valid X-API-KEY header.
 *
 *   no header / wrong key -> 401 (JSON body)
 *   correct key           -> request is served normally
 *
 * The expected key is "test-secret-key" (phpunit.dist.xml -> API_KEY).
 */
final class ApiAuthenticationTest extends WebTestCase
{
    private const string VALID_KEY = 'test-secret-key';

    private KernelBrowser $client;

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
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $connection = $em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['blacklist_entry', 'driver_history_entry', 'driver', 'region'] as $table) {
            $connection->executeStatement("TRUNCATE TABLE {$table}");
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $region = (new Region())->setCode('UA-51')->setName('Odesa Oblast');
        $em->persist($region);
        $em->persist((new Driver())->setLastName('Иванов')->setFirstName('Иван')->setRegion($region));
        $em->flush();
        $em->clear();
    }

    public function testRequestWithoutApiKeyIsRejected(): void
    {
        $this->client->request('GET', '/api/drivers/search?query=Иванов');

        self::assertResponseStatusCodeSame(401);
    }

    public function testRequestWithWrongApiKeyIsRejected(): void
    {
        $this->client->request('GET', '/api/drivers/search?query=Иванов', server: [
            'HTTP_X_API_KEY' => 'wrong-key',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnauthorizedBodyIsJson(): void
    {
        $this->client->request('GET', '/api/drivers/search?query=Иванов', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertJson((string) $this->client->getResponse()->getContent());
    }

    public function testRequestWithValidApiKeyPasses(): void
    {
        $this->client->request('GET', '/api/drivers/search?query=Иванов', server: [
            'HTTP_X_API_KEY' => self::VALID_KEY,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $data);
        self::assertSame('Иванов', $data[0]['lastName']);
    }
}
