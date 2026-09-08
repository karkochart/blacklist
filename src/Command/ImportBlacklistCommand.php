<?php

declare(strict_types=1);

namespace App\Command;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-blacklist',
    description: 'Import drivers and blacklist entries from the source xlsx file',
)]
final class ImportBlacklistCommand extends Command
{
    /** Sheet name inside the source workbook (the other sheets are irrelevant). */
    private const string SHEET_NAME = 'ЧЕРНЫЙ СПИСОК ВОДИТЕЛЕЙ';

    /** First row holds the column titles, data starts at row 2. */
    private const int FIRST_DATA_ROW = 2;

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the source xlsx file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse and report only, do not write to the database')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Process only the first N data rows');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file = (string) $input->getArgument('file');
        if (!is_file($file) || !is_readable($file)) {
            $io->error(sprintf('File not found or not readable: %s', $file));

            return Command::INVALID;
        }

        $limit = $input->getOption('limit');
        $limit = $limit === null ? null : max(1, (int) $limit);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SHEET_NAME]);
        $sheet = $reader->load($file)->getActiveSheet();

        $total = 0;
        $empty = 0;
        $separators = 0;
        $data = 0;

        foreach ($sheet->getRowIterator(self::FIRST_DATA_ROW) as $row) {
            $cells = $row->getCellIterator('A', 'D');
            $cells->setIterateOnlyExistingCells(false);

            $values = [];
            foreach ($cells as $cell) {
                $values[$cell->getColumn()] = trim((string) $cell->getValue());
            }

            $name = $values['C'] ?? '';
            $isRowEmpty = '' === implode('', $values);

            ++$total;
            if ($isRowEmpty) {
                ++$empty;
            } elseif (mb_strlen($name) <= 1) {
                // single Cyrillic letter — an alphabet divider in the source, not a person
                ++$separators;
            } else {
                ++$data;
            }

            if (null !== $limit && $data >= $limit) {
                break;
            }
        }

        $io->title(sprintf('Blacklist import%s', $input->getOption('dry-run') ? ' (dry run)' : ''));
        $io->definitionList(
            ['Source' => $file],
            ['Sheet' => self::SHEET_NAME],
            ['Rows scanned' => $total],
            ['Data rows' => $data],
            ['Alphabet dividers' => $separators],
            ['Empty rows' => $empty],
        );

        $io->success('Sheet read successfully.');

        return Command::SUCCESS;
    }
}
