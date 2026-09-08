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
        'UA-05' => 'Vinnytsia Oblast',
        'UA-07' => 'Volyn Oblast',
        'UA-09' => 'Luhansk Oblast',
        'UA-12' => 'Dnipropetrovsk Oblast',
        'UA-14' => 'Donetsk Oblast',
        'UA-18' => 'Zhytomyr Oblast',
        'UA-21' => 'Zakarpattia Oblast',
        'UA-23' => 'Zaporizhzhia Oblast',
        'UA-26' => 'Ivano-Frankivsk Oblast',
        'UA-30' => 'Kyiv',
        'UA-32' => 'Kyiv Oblast',
        'UA-35' => 'Kirovohrad Oblast',
        'UA-40' => 'Sevastopol',
        'UA-43' => 'Autonomous Republic of Crimea',
        'UA-46' => 'Lviv Oblast',
        'UA-48' => 'Mykolaiv Oblast',
        'UA-51' => 'Odesa Oblast',
        'UA-53' => 'Poltava Oblast',
        'UA-56' => 'Rivne Oblast',
        'UA-59' => 'Sumy Oblast',
        'UA-61' => 'Ternopil Oblast',
        'UA-63' => 'Kharkiv Oblast',
        'UA-65' => 'Kherson Oblast',
        'UA-68' => 'Khmelnytskyi Oblast',
        'UA-71' => 'Cherkasy Oblast',
        'UA-74' => 'Chernihiv Oblast',
        'UA-77' => 'Chernivtsi Oblast',
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

        /** @var list<string> $existing */
        $existing = array_column(
            $this->regions->createQueryBuilder('r')->select('r.code')->getQuery()->getScalarResult(),
            'code',
        );

        $created = 0;
        foreach (self::REGIONS as $code => $name) {
            if (\in_array($code, $existing, true)) {
                continue;
            }
            $this->em->persist(
                (new Region())->setCode($code)->setName($name),
            );
            ++$created;
        }

        $this->em->flush();

        $io->success(sprintf(
            '%d region(s) created, %d already present, %d total.',
            $created,
            \count(self::REGIONS) - $created,
            \count(self::REGIONS),
        ));

        return Command::SUCCESS;
    }
}
