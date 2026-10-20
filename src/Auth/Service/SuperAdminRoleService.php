<?php

namespace App\Auth\Service;

use App\Auth\Entity\Role;
use App\Auth\Roles;
use Doctrine\ORM\EntityManagerInterface;

class SuperAdminRoleService
{
    private ?int $superAdminRoleId = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function getSuperAdminRoleId(): ?int
    {
        if ($this->superAdminRoleId === null) {
            $repo = $this->entityManager->getRepository(Role::class);
            $role = $repo->findOneBy(['name' => Roles::ROLE_SUPER_ADMIN]);
            $this->superAdminRoleId = $role->getId();
        }

        return $this->superAdminRoleId;
    }
}
