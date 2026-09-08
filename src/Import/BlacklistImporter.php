<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\BlacklistEntry;
use App\Entity\Driver;
use App\Entity\Region;
use App\Repository\BlacklistEntryRepository;
use App\Repository\DriverRepository;
use App\Repository\RegionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;

/**
 * Reads the blacklist worksheet and turns each valid row into a Driver
 * (deduplicated) plus a BlacklistEntry, writing to the database in batches.
 *
 * Resumable: every entry is tagged with its source row, so re-running the
 * import skips what already landed and only creates the missing/failed rows.
 */
final class BlacklistImporter
{
    /** Sheet name inside the source workbook (the other sheets are irrelevant). */
    private const string SHEET_NAME = 'ЧЕРНЫЙ СПИСОК ВОДИТЕЛЕЙ';

    /** Row 1 holds the column titles. */
    private const int FIRST_DATA_ROW = 2;

    /** All rows in this file belong to the Odesa community (ISO 3166-2:UA). */
    private const string REGION_CODE = 'UA-51';

    private const int BATCH_SIZE = 200;

    private bool $dryRun = false;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DriverNameParser $nameParser,
        private readonly RegionRepository $regions,
        private readonly DriverRepository $drivers,
        private readonly BlacklistEntryRepository $blacklistEntries,
    ) {
    }

    /**
     * @param string   $file   path to the xlsx file
     * @param bool     $dryRun when true, nothing is written to the database
     * @param int|null $limit  process at most this many data rows
     */
    public function import(string $file, bool $dryRun = false, ?int $limit = null): ImportReport
    {
        $this->dryRun = $dryRun;
        $report = new ImportReport();

        $regionId = $this->requireRegion()->getId();
        $sheet = $this->openSheet($file);
        $sourceName = basename($file);

        /** @var array<string, string> $alreadyImported  set of sourceReference values */
        $alreadyImported = array_fill_keys($this->importedReferences(), true);

        /** @var array<string, Driver> $driverCache  natural key => Driver (valid until the next clear()) */
        $driverCache = [];
        $region = $this->em->getReference(Region::class, $regionId);
        $batch = 0;

        foreach ($sheet->getRowIterator(self::FIRST_DATA_ROW) as $row) {
            if (null !== $limit && $report->rowsScanned >= $limit) {
                break;
            }

            $cells = $this->readRow($row);
            $rawName = $cells['C'];
            if ('' === $rawName || mb_strlen($rawName) <= 1) {
                continue; // blank row or a single-letter alphabet divider
            }
            ++$report->rowsScanned;

            $reference = sprintf('%s#row-%d', $sourceName, $row->getRowIndex());
            if (isset($alreadyImported[$reference])) {
                ++$report->skipped;
                continue;
            }

            try {
                $this->importRow($cells, $reference, $region, $driverCache, $report);
            } catch (\Throwable $e) {
                $report->addError($row->getRowIndex(), $e->getMessage());
                if (!$this->dryRun) {
                    $this->em->clear();
                    $driverCache = [];
                    $region = $this->em->getReference(Region::class, $regionId);
                }
                continue;
            }

            if (!$this->dryRun && 0 === (++$batch % self::BATCH_SIZE)) {
                $this->em->flush();
                $this->em->clear();
                $driverCache = [];
                $region = $this->em->getReference(Region::class, $regionId);
            }
        }

        if (!$this->dryRun) {
            $this->em->flush();
        }

        return $report;
    }

    /**
     * @param array{A: string, B: string, C: string, D: string} $cells
     * @param array<string, Driver>                              $driverCache
     */
    private function importRow(array $cells, string $reference, Region $region, array &$driverCache, ImportReport $report): void
    {
        $name = $this->nameParser->parse($cells['C']);
        if ('' === $name->lastName || '' === $name->firstName) {
            throw new \RuntimeException(sprintf('cannot parse a name from "%s"', $cells['C']));
        }

        $driver = $this->resolveDriver($name, $region, $driverCache, $report);

        $entry = (new BlacklistEntry())
            ->setDriver($driver)
            ->setReportedBy($this->clean($cells['B'], 255))
            ->setText($this->clean($cells['D']))
            ->setOccurredAt($this->parseExcelDate($cells['A']))
            ->setSourceReference($reference);

        if (!$this->dryRun) {
            $this->em->persist($entry);
        }
        ++$report->blacklistEntriesCreated;
    }

    /**
     * @param array<string, Driver> $driverCache
     */
    private function resolveDriver(ParsedName $name, Region $region, array &$driverCache, ImportReport $report): Driver
    {
        $key = mb_strtolower(implode('|', [
            $name->lastName,
            $name->firstName,
            $name->middleName ?? '',
            $name->birthDate?->format('Y-m-d') ?? '',
        ]), 'UTF-8');

        if (isset($driverCache[$key])) {
            return $driverCache[$key];
        }

        $driver = $this->drivers->findOneBy([
            'lastName' => $name->lastName,
            'firstName' => $name->firstName,
            'middleName' => $name->middleName,
            'birthDate' => $name->birthDate,
        ]);

        if (null === $driver) {
            $driver = (new Driver())
                ->setLastName($name->lastName)
                ->setFirstName($name->firstName)
                ->setMiddleName($name->middleName)
                ->setBirthDate($name->birthDate)
                ->setRegion($region);

            if (!$this->dryRun) {
                $this->em->persist($driver);
            }
            ++$report->driversCreated;
        }

        return $driverCache[$key] = $driver;
    }

    private function requireRegion(): Region
    {
        return $this->regions->findOneByCode(self::REGION_CODE)
            ?? throw new \RuntimeException(sprintf(
                'Region "%s" is missing — run "php bin/console app:seed-regions" first.',
                self::REGION_CODE,
            ));
    }

    private function openSheet(string $file): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SHEET_NAME]);

        $sheet = $reader->load($file)->getSheetByName(self::SHEET_NAME);
        if (null === $sheet) {
            throw new \RuntimeException(sprintf('Sheet "%s" not found in %s', self::SHEET_NAME, $file));
        }

        return $sheet;
    }

    /**
     * @return list<string>
     */
    private function importedReferences(): array
    {
        /** @var list<string> $refs */
        $refs = $this->blacklistEntries->createQueryBuilder('e')
            ->select('e.sourceReference')
            ->where('e.sourceReference IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();

        return $refs;
    }

    /**
     * @return array{A: string, B: string, C: string, D: string}
     */
    private function readRow(Row $row): array
    {
        $out = ['A' => '', 'B' => '', 'C' => '', 'D' => ''];

        $cells = $row->getCellIterator('A', 'D');
        $cells->setIterateOnlyExistingCells(false);
        foreach ($cells as $cell) {
            /** @var Cell $cell */
            $out[$cell->getColumn()] = trim((string) preg_replace('~\s+~u', ' ', (string) $cell->getValue()));
        }

        return $out;
    }

    private function clean(string $value, ?int $maxLength = null): ?string
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }
        if (null !== $maxLength && mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    private function parseExcelDate(string $raw): ?\DateTimeImmutable
    {
        if (!is_numeric($raw)) {
            return null;
        }

        $serial = (float) $raw;
        if ($serial <= 1.0 || $serial >= 60000.0) {   // ~1900..2064; also rejects the one corrupt cell
            return null;
        }

        return \DateTimeImmutable::createFromInterface(ExcelDate::excelToDateTimeObject($serial))
            ->setTime(0, 0);
    }
}
