<?php

/*
This funcition permanent delete users soft marked for deletion using the UI.
This action is irreversible and the user would not be recovered after this.
*/

namespace App\Auth\Command;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:permanent-delete-users', description: 'Permanently delete soft-deleted users')]
class PermanentDeleteUsers extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'force', // This is the name of the option, "--force"
                null,    // An optional shortcut (e.g., '-f'). We have none.
                InputOption::VALUE_NONE, // This means it's a flag; it doesn't take a value (e.g., --force=true)
                'Bypass the confirmation prompt and run non-interactively'
            )
            ->setHelp('This command permanently deletes users marked with isDeleted=true. It is a destructive operation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Safety first: Ask for confirmation for destructive commands
        if (!$input->getOption('force')) {
            $io->warning('This action is irreversible and will permanently delete users.');
            if (!$io->confirm('Are you sure you want to proceed?', false)) {
                $io->info('Purge operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $io->title('Purging Deleted Users...');

        // This single, efficient query replaces your entire old try/catch block.
        // It tells the database to delete all users where isDeleted is true.
        $query = $this->entityManager->createQuery(
            'DELETE FROM App\Auth\Entity\UserEntity u WHERE u.isDeleted = :isDeleted'
        )->setParameter('isDeleted', true);

        $numberOfDeletedUsers = $query->execute();

        if ($numberOfDeletedUsers > 0) {
            $io->success(sprintf('Successfully purged %d user(s).', $numberOfDeletedUsers));
        } else {
            $io->info('No deleted users found to purge.');
        }

        return Command::SUCCESS;
    }
}
