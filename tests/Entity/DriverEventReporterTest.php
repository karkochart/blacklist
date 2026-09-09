<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\BlacklistEntry;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * getReporterName() prefers the linked account, falls back to the imported free text.
 */
final class DriverEventReporterTest extends TestCase
{
    public function testUsesAccountNameWhenReporterIsSet(): void
    {
        $entry = (new BlacklistEntry())
            ->setReporter((new User())->setName('Karen Admin'))
            ->setReportedBy('legacy nickname');

        self::assertSame('Karen Admin', $entry->getReporterName());
    }

    public function testFallsBackToImportedTextWhenNoReporter(): void
    {
        $entry = (new BlacklistEntry())->setReportedBy('Денис');

        self::assertSame('Денис', $entry->getReporterName());
    }

    public function testNullWhenNeitherIsSet(): void
    {
        self::assertNull((new BlacklistEntry())->getReporterName());
    }
}
