<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Create or promote an admin user.')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Admin email')
            ->addArgument('name', InputArgument::REQUIRED, 'Admin display name')
            ->addArgument('password', InputArgument::REQUIRED, 'Plain password (will be hashed)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $name = (string) $input->getArgument('name');
        $password = (string) $input->getArgument('password');

        $user = $this->users->findByEmail($email) ?? new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setRole(UserRole::Admin);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->users->save($user);

        $io->success(\sprintf('Admin "%s" saved with ROLE_ADMIN.', $email));

        return Command::SUCCESS;
    }
}
