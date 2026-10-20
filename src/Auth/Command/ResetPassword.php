<?php

/*
This function updates a user password.
*/

namespace App\Auth\Command;

use App\Auth\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:reset-password', description: "This function resets a user password.")]

class ResetPassword extends Command
{
    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Please, enter the user E-mail:')
            ->addArgument('password', InputArgument::REQUIRED, 'Please, enter a secure password:');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = mb_strtolower($input->getArgument('email'));
        $password = $input->getArgument('password');

        $user = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $io->writeln("An error occurred resetting password: unable to find user by email '$email'");
            return Command::FAILURE;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user,$password);
        $user->setPassword($hashedPassword);

        $this->entityManager->flush();

        $io->success('The user password was updated.');

        return Command::SUCCESS;
    }
}
