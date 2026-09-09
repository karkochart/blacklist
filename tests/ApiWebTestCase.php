<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base for functional tests that hit the /api firewall.
 *
 * - builds the schema once per test class
 * - truncates the working tables before each test
 * - sends a valid X-API-KEY on every request by default (see API_KEY, matches phpunit.dist.xml)
 */
abstract class ApiWebTestCase extends WebTestCase
{
    protected const string API_KEY = 'test-secret-key';

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

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
        $this->client->setServerParameter('HTTP_X_API_KEY', self::API_KEY);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->truncate('blacklist_entry', 'driver_history_entry', 'driver', 'region');
    }

    protected function truncate(string ...$tables): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $connection->executeStatement("TRUNCATE TABLE {$table}");
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
