<?php

namespace App\Auth\Command;

use App\Auth\Entity\Role;
use App\Auth\Entity\UserEntity;
use App\Auth\Roles;
use App\Auth\Service\GlobalRoleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:create-superadmin', description: 'Creates a new superadmin user.')]
class CreateSuperAdmin extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly GlobalRoleService $roleService,
        private readonly string $defaultUserTimezone,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a super admin user account.')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'The email of the user.')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'The password of the user.')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'The first name of the user.')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'The last name of the user.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Fetch or create the ROLE_SUPER_ADMIN Role entity.
        try {
            $superAdminRole = $this->roleService->getRole(Roles::ROLE_SUPER_ADMIN);
        } catch (\RuntimeException $ex) {
            $this->roleService->initializeRoles();
            $superAdminRole = $this->roleService->getRole(Roles::ROLE_SUPER_ADMIN);
        }

        $email = mb_strtolower($input->getOption('email'));
        $password = $input->getOption('password');
        $firstName = $input->getOption('first-name');
        $lastName = $input->getOption('last-name');
        $fullName = "$firstName $lastName";

        $user = new UserEntity();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setTimezone($this->defaultUserTimezone);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Assign the global super admin role and set primary organization to null (for superadmins)
        $user->setGlobalRole($superAdminRole);
        $user->setPrimaryOrganization(null); // Explicitly set to null for Superadmins

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getMessage());
            }
            return Command::FAILURE;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('The Superadmin user was successfully created.');

        return Command::SUCCESS;
    }
}
