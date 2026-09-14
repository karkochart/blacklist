<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Region;
use App\Repository\RegionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-regions',
    description: 'Insert the administrative regions of Ukraine into the region table (idempotent)',
)]
final class SeedRegionsCommand extends Command
{
    /**
     * Top-level administrative units of Ukraine: 24 oblasts, the Autonomous
     * Republic of Crimea, and the two cities with special status.
     * Keyed by their ISO 3166-2:UA code.
     *
     * @var array<string, string>
     */
    private const array REGIONS = [
        'UA-05' => 'Вінницька область',
        'UA-07' => 'Волинська область',
        'UA-09' => 'Луганська область',
        'UA-12' => 'Дніпропетровська область',
        'UA-14' => 'Донецька область',
        'UA-18' => 'Житомирська область',
        'UA-21' => 'Закарпатська область',
        'UA-23' => 'Запорізька область',
        'UA-26' => 'Івано-Франківська область',
        'UA-30' => 'Київ',
        'UA-32' => 'Київська область',
        'UA-35' => 'Кіровоградська область',
        'UA-40' => 'Севастополь',
        'UA-43' => 'Автономна Республіка Крим',
        'UA-46' => 'Львівська область',
        'UA-48' => 'Миколаївська область',
        'UA-51' => 'Одеська область',
        'UA-53' => 'Полтавська область',
        'UA-56' => 'Рівненська область',
        'UA-59' => 'Сумська область',
        'UA-61' => 'Тернопільська область',
        'UA-63' => 'Харківська область',
        'UA-65' => 'Херсонська область',
        'UA-68' => 'Хмельницька область',
        'UA-71' => 'Черкаська область',
        'UA-74' => 'Чернігівська область',
        'UA-77' => 'Чернівецька область',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RegionRepository $regions,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var array<string, Region> $existing */
        $existing = [];
        foreach ($this->regions->findAll() as $region) {
            $existing[$region->getCode()] = $region;
        }

        $created = 0;
        $renamed = 0;
        foreach (self::REGIONS as $code => $name) {
            $region = $existing[$code] ?? null;

            if ($region === null) {
                $this->em->persist((new Region())->setCode($code)->setName($name));
                ++$created;

                continue;
            }

            if ($region->getName() !== $name) {
                $region->setName($name);
                ++$renamed;
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            '%d region(s) created, %d renamed, %d unchanged, %d total.',
            $created,
            $renamed,
            \count(self::REGIONS) - $created - $renamed,
            \count(self::REGIONS),
        ));

        return Command::SUCCESS;
    }
}
