<?php

namespace App\Auth\Command;

use App\Auth\Entity\Role;
use App\Auth\Roles;
use App\Auth\Service\GlobalRoleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:init-roles', description: "Creates all default application roles if they don't already exist.")]
class InitializeRolesCommand extends Command
{
    public function __construct(
        private readonly GlobalRoleService $roleService
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $onCreated = function ($roleName) use ($io) {
            $io->writeln("Creating role: $roleName");
        };

        $numRolesCreated = $this->roleService->initializeRoles($onCreated);

        if ($numRolesCreated > 0) {
            $io->success("$numRolesCreated role(s) were successfully created.");
        } else {
            $io->note('All roles already exist in the database. No action taken.');
        }

        return Command::SUCCESS;
    }
}
