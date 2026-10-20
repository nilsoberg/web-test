<?php

/*
This funcition permanent delete organizations soft marked for deletion using the UI.
This action is irreversible ok. Use it with care.
*/

namespace App\Auth\Command;

use App\Auth\Entity\OrganizationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:permanent-delete-organizations', description: 'Permanently delete soft-deleted organizations')]
class PermanentDeleteOrganizations extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $organizationRepository = $this->entityManager->getRepository(OrganizationEntity::class);
        $deletedOrganizations = $organizationRepository->findBy(['isDeleted' => true]);

        foreach ($deletedOrganizations as $organization) {
            $this->entityManager->remove($organization);
        }

        $this->entityManager->flush();

        $output->writeln('Soft-deleted organizations have been permanently deleted.');

        return Command::SUCCESS;
    }
}