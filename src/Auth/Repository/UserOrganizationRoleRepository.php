<?php

/* This repository will help access information about user roles and organizations. */

namespace App\Auth\Repository;

use App\Auth\Entity\UserOrganizationRole;
use App\Auth\Entity\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserOrganizationRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserOrganizationRole::class);
    }

    /**
     * Finds UserOrganizationRole entities by User and Role
     *
     * @param UserEntity $user
     * @param string $role
     * @return UserOrganizationRole[]
     */
    public function findByUserAndRole(UserEntity $user, string $role): array
    {
        return $this->createQueryBuilder('uor')
            ->andWhere('uor.user = :user')
            ->andWhere('uor.role = :role')
            ->setParameter('user', $user)
            ->setParameter('role', $role)
            ->getQuery()
            ->getResult();
    }
}