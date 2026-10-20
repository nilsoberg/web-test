<?php

namespace App\Service;

use App\Auth\Entity\OrganizationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class OrganizationManagementService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function create(OrganizationEntity $organization): void
    {
        try {
            $this->entityManager->persist($organization);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Catch, log, and rethrow database exceptions
            $this->logger->error('Database error encountered when creating organization', [
                'org_name' => $organization->getName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function edit(OrganizationEntity $organization): void
    {
        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Catch, log, and rethrow database exceptions
            $this->logger->error('Database error encountered when editing organization', [
                'org_name' => $organization->getName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
