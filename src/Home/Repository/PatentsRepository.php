<?php

namespace App\Home\Repository;

use App\Home\Entity\PatentsEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PatentsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatentsEntity::class);
    }

    public function findAll(): array
    {
        return $this->findBy([], ['year' => 'DESC']);
    }
}