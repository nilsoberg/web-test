<?php

namespace App\EventListener;

use App\Auth\Entity\OrganizationEntity;
use App\Entity\DatabaseMetadata;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: DatabaseMetadata::class)]
class DatabaseMetadataOrganizationChangeListener
{
    public function preUpdate(DatabaseMetadata $database, PreUpdateEventArgs $event): void
    {
        // Check if the organization field actually changed during this update
        if ($event->hasChangedField('organization')) {

            /** @var OrganizationEntity|null $oldOrganization */
            $oldOrganization = $event->getOldValue('organization');

            // If it previously belonged to an organization, check its default status
            if ($oldOrganization !== null) {
                $currentDefault = $oldOrganization->getDefaultDatabase();

                // If this database was the default for the old organization, sever the link
                if ($currentDefault === $database) {
                    $oldOrganization->setDefaultDatabase(null);

                    $em = $event->getObjectManager();

                    $em->createQueryBuilder()
                        ->update(OrganizationEntity::class, 'o')
                        ->set('o.defaultDatabase', 'NULL')
                        ->where('o.id = :orgId')
                        ->setParameter('orgId', $oldOrganization->getId())
                        ->getQuery()
                        ->execute();
                }
            }
        }
    }
}
