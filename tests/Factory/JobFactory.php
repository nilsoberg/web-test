<?php

namespace App\Tests\Factory;

use App\Entity\Job;
use App\Enum\JobStatus;
use App\Service\ResultFileService;
use Symfony\Component\DependencyInjection\Attribute\Required;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

abstract class JobFactory extends PersistentProxyObjectFactory
{
    public function __construct(
        protected readonly ResultFileService $fileService,
		protected readonly ParameterBagInterface $params,
	)
    {
        parent::__construct();
    }

    protected function defaults(): array|callable
    {
        return [
            'jobName' => self::faker()->words(3, true),
            'status' => self::faker()->randomElement(JobStatus::cases()),
            'timeCreated' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 month', 'now')),
            'isPublic' => self::faker()->boolean(20),
            'dbVersion' => 'v2026.1',
        ];
    }

    protected function initializeJobDir(Job $job, ?array $fileTypes = null): void
    {
        if ($fileTypes === null) {
            $fileTypes = [
                'length_histogram_uniprot',
                'length_histogram_uniprot_json',
                'alignment_length',
                'alignment_length_json',
                'percent_identity',
                'percent_identity_json',
                'number_of_edges',
                'number_of_edges_json',
                'length_histogram_uniref90',
                'length_histogram_uniref90_json',
                'length_histogram_uniref90_domain',
                'length_histogram_uniref90_domain_json',
                'length_histogram_uniref50',
                'length_histogram_uniref50_json',
                'length_histogram_uniref50_domain',
                'length_histogram_uniref50_domain_json',
                'length_histogram_uniprot_domain',
                'length_histogram_uniprot_domain_json',
                'est_edges',
                'sunburst_json',
            ];
        }

        $baseResultsDir = $this->params->get('app.results_directory');
        
        // Build the specific job path
        $jobDir = $baseResultsDir . '/' . $job->getId() . '/results';

        // Create the directory and mock files
        $fs = new Filesystem();
        if (!$fs->exists($jobDir)) {
            $fs->mkdir($jobDir);
        }

        // Create dummy files
        foreach ($fileTypes as $fileType) {
            $path = $this->fileService->getResultFilePath($job, $fileType, false);
            if (str_ends_with($path, '.json')) {
                $fs->dumpFile($path, json_encode(['mock' => true]));
            } else {
                $fs->touch($path);
            }
        }
    }
}
