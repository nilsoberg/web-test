<?php

namespace App\Auth\Service;

use App\Auth\Entity\Role;
use App\Auth\Roles;
use Doctrine\ORM\EntityManagerInterface;

class GlobalRoleService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {
    }

    public function initializeRoles(?callable $onRoleCreated = null): int
    {
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $rolesCreated = 0;

        foreach (Roles::getOrgRoles() as $roleName) {
            // Check if the role already exists
            if (!$roleRepository->findOneBy(['name' => $roleName])) {
                $role = new Role();
                $role->setName($roleName);
                $this->entityManager->persist($role);
                if ($onRoleCreated) {
                    $onRoleCreated($roleName);
                }
                $rolesCreated++;
            }
        }

        if ($rolesCreated > 0) {
            $this->entityManager->flush();
        }

        return $rolesCreated;
    }

    public function getRole(string $roleName): ?Role
    {
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $roleName]);

        if (!$role) {
            throw new \RuntimeException(
                sprintf(
                    'Configuration Error: Global role "%s" was not found in the database. ' .
                    'Please ensure database migrations and seed commaneds have run successfully.',
                    $roleName
                )
            );
        }

        return $role;
    }
}
