<?php

namespace App\Repository;

use App\Entity\SiteNotice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SiteNoticeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteNotice::class);
    }

    public function findActiveNotice(): ?SiteNotice
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('n.activatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
