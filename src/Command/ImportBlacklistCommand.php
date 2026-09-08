<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\BlacklistImporter;
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
    public function __construct(
        private readonly BlacklistImporter $importer,
    ) {
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

        $limitOption = $input->getOption('limit');
        $limit = null === $limitOption ? null : max(1, (int) $limitOption);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title(sprintf('Blacklist import%s', $dryRun ? ' (dry run)' : ''));

        try {
            $report = $this->importer->import($file, $dryRun, $limit);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Source' => $file],
            ['Rows processed' => $report->rowsScanned],
            ['Drivers created' => $report->driversCreated],
            ['Blacklist entries created' => $report->blacklistEntriesCreated],
            ['Skipped (already imported)' => $report->skipped],
            ['Errors' => \count($report->errors)],
        );

        if ([] !== $report->errors) {
            $io->warning(sprintf('%d row(s) could not be imported.', \count($report->errors)));
            if ($output->isVerbose()) {
                $io->listing($report->errors);
            } else {
                $io->comment('Re-run with -v to list them.');
            }
        }

        $io->success($dryRun ? 'Dry run complete — nothing was written.' : 'Import complete.');

        return Command::SUCCESS;
    }
}
