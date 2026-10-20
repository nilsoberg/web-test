<?php

namespace App\EventListener;

use App\Config\Settings;
use App\Entity\CGFPQuantifyJob;
use App\Entity\Job;
use App\Repository\DatabaseMetadataRepository;
use App\Service\ConfigurationManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Job::class)]
class JobDatabaseVersionListener
{
    public function __construct(
        private readonly ConfigurationManager $config,
        private readonly DatabaseMetadataRepository $dbRepo, // Inject repository to look up global default entity
        private readonly bool $strictMode, // Set to false to bypass database configuration consistency check for unit tests
    )
    {
    }

    public function prePersist(Job $job, PrePersistEventArgs $event): void
    {
        // User is submitting a Quantify job and selected a metagenome database
        if ($job instanceof CGFPQuantifyJob) {
            if ($job->getMetagenomeDatabase() !== null) {
                // Automatically mirror the string name to the metagenomeDbVersion column that
                // the job manager uses
                $job->setMetagenomeDbVersion($job->getMetagenomeDatabase()->getDbName());
            }
        }

        // User selected a EFI database version in the form
        if ($job->getDbMetadata() !== null) {
            // Automatically mirror the string name to the dbVersion the job manager expects
            $job->setDbVersion($job->getDbMetadata()->getDbName());
            return;
        }

        // Set the EFI database version to the default database if it hasn't been set by the user
        if ($job->getDbVersion() === null) {
            $user = $job->getUser();
            $org = $user ? $user->getPrimaryOrganization() : null;

            if ($org && $org->getDefaultDatabase() !== null) {
                $defaultDb = $org->getDefaultDatabase();
                $job->setDbMetadata($defaultDb);
                $job->setDbVersion($defaultDb->getDbName());
                return;
            }

            $defaultDbVersion = $this->config->get(Settings::DEFAULT_DATABASE_VERSION);
            $defaultDbMetadata = $this->dbRepo->findOneBy(['dbName' => $defaultDbVersion]);
            if ($defaultDbMetadata !== null) {
                $job->setDbMetadata($defaultDbMetadata);
                $job->setDbVersion($defaultDbMetadata->getDbName());
            } else if ($this->strictMode) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid configuration: The default database version "%s" specified in the configuration file is not present in the database. ' .
                    'Please ensure the default database version matches a database listed in the Database Admin tool.', 
                    $defaultDbVersion,
                ));
            } else {
                // In non strict mode, assign default database version anyway
                $job->setDbVersion($defaultDbVersion);
            }
        }
    }
}
