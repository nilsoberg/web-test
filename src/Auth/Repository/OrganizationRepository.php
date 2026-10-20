<?php

/* This repository will help access information about organizations. */

namespace App\Auth\Repository;

use App\Auth\Entity\OrganizationEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationEntity::class);
    }
}