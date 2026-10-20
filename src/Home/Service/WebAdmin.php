<?php

namespace App\Home\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Home\Entity\PublicDB;
use App\Home\Entity\PublicationsEntity;
use App\Home\Entity\PatentsEntity;

class WebAdmin
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
         $this->entityManager = $entityManager;
    }

    public function getPublicDatabaseInfo(): ?array
    {
         $public_databases_repository = $this->entityManager->getRepository(PublicDB::class);
         $activeEntry = $public_databases_repository->findOneBy(['active' => true]);

         if ($activeEntry) {
             return [
                'uniprot' => $activeEntry->getUniprot(),
                'interpro' => $activeEntry->getInterpro(),
                'ena'      => $activeEntry->getEna(),
             ];
         }

         return null;
    }

    public function getPublicationsInfo(): ?array
    {
         $publications_repository = $this->entityManager->getRepository(PublicationsEntity::class);

         if ($publications_repository) {
             return [
                'author' => $publications_repository->getAuthor(),
                'title' => $publications_repository->getTitle(),
                'secondary_title' => $publications_repository->getSecondaryTitle(),
                'publisher' => $publications_repository->getPublisher(),
                'year' => $publications_repository->getYear(),
                'edition' => $publications_repository->getEdition(),
                'number' => $publications_repository->getNumber(),
                'pages'      => $publications_repository->getPages(),
                'url' => $publications_repository->getUrl()
             ];
         }

         return null;
    }

    public function getPatentsInfo(): ?array
    {
         $patents_repository = $this->entityManager->getRepository(PatentsEntity::class);

         if ($patents_repository) {
             return [
                'number' => $patents_repository->getNumber(),
                'title' => $patents_repository->getTitle(),
                'author' => $patents_repository->getAuthor(),
                'year' => $patents_repository->getYear(),
                'month' => $patents_repository->getMonth(),
                'day' => $patents_repository->getDay()
             ];
         }

         return null;
    }


}