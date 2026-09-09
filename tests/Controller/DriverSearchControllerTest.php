<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\BlacklistEntry;
use App\Entity\Driver;
use App\Entity\Region;
use App\Tests\ApiWebTestCase;

/**
 * Functional test for GET /api/drivers/search.
 *
 * Contract:
 *   GET /api/drivers/search?query=<term>
 *   - 200 application/json, body is a JSON array of matching drivers
 *   - each driver: id, lastName, firstName, middleName, birthDate, licenseNumber,
 *     region { code, name }, blacklistEntries [ { reportedBy, text, occurredAt, isActive } ]
 *   - no match -> 200 []
 *   (missing / short query -> 422, covered by DriverSearchValidationTest)
 */
final class DriverSearchControllerTest extends ApiWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    // Query-parameter validation (missing/short/limit) is covered by DriverSearchValidationTest.

    public function testNoMatchReturnsEmptyArray(): void
    {
        self::assertSame([], $this->search('?query=Ковальчук'));
    }
}
