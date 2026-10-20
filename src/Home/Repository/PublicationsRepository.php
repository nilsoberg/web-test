<?php

namespace App\Home\Repository;

use App\Home\Entity\PublicationsEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PublicationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationsEntity::class);
    }

    public function findAll(): array
    {
        return $this->findBy([], ['year' => 'DESC']);
    }
}