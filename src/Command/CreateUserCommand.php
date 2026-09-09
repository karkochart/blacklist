<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates a user account. There is no public registration — this is how the
 * first admin is bootstrapped, and how new operators are added.
 *
 *   php bin/console app:create-user admin@example.com "Karen" --admin
 */
#[AsCommand(
    name: 'app:create-user',
    description: 'Create a user account (interactive password prompt)',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Login email')
            ->addArgument('name', InputArgument::REQUIRED, 'Display name (shown as "who reported it")')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant ROLE_ADMIN')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password (omit to be prompted, hidden)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getArgument('email');
        $name = (string) $input->getArgument('name');

        if (null !== $this->users->findOneBy(['email' => $email])) {
            $io->error(sprintf('A user with email "%s" already exists.', $email));

            return Command::INVALID;
        }

        $plainPassword = $input->getOption('password')
            ?? $io->askHidden('Password', static function (?string $value): string {
                if (null === $value || \strlen($value) < 8) {
                    throw new \RuntimeException('Password must be at least 8 characters.');
                }

                return $value;
            });

        $user = (new User())
            ->setEmail($email)
            ->setName($name)
            ->setRoles($input->getOption('admin') ? ['ROLE_ADMIN'] : []);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf(
            'Created %s "%s" <%s>.',
            $input->getOption('admin') ? 'admin' : 'user',
            $name,
            $email,
        ));

        return Command::SUCCESS;
    }
}
