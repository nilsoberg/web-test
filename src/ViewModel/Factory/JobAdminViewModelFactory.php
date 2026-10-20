<?php

namespace App\ViewModel\Factory;

use App\CGFP\Controller\CGFPController;
use App\Entity\CGFPIdentifyJob;
use App\Entity\CGFPQuantifyJob;
use App\Entity\ESTClusterAnalysisJob;
use App\Entity\ESTColorSSNJob;
use App\Entity\ESTConvergenceRatioJob;
use App\Entity\ESTGenerateAccessionJob;
use App\Entity\ESTGenerateBlastJob;
use App\Entity\ESTGenerateFamiliesJob;
use App\Entity\ESTGenerateFastaJob;
use App\Entity\ESTGenerateJob;
use App\Entity\ESTNeighborhoodConnectivityJob;
use App\Entity\ESTSSNFinalizationJob;
use App\Entity\GNTGNNJob;
use App\Entity\GNTDiagramBlastJob;
use App\Entity\GNTDiagramFastaJob;
use App\Entity\GNTDiagramJob;
use App\Entity\GNTDiagramSequenceIdJob;
use App\Entity\GNTViewDiagramJob;
use App\Entity\Job;
use App\Entity\TaxonomyAccessionJob;
use App\Entity\TaxonomyFamiliesJob;
use App\Entity\TaxonomyFastaJob;
use App\Entity\TaxonomyJob;
use App\Enum\JobStatus;
use App\EST\Controller\ESTResultController;
use App\EST\Controller\SSNUtilityController;
use App\GNT\Controller\GNDController;
use App\GNT\Controller\GNTController;
use App\Service\StateChangeFormValidationService;
use App\Taxonomy\Controller\TaxonomyController;
use App\ViewModel\JobAdminViewModel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class JobAdminViewModelFactory
{
    private const JOB_TYPE_NAME = [
        ESTGenerateBlastJob::class => 'EST/BLAST',
        ESTGenerateFamiliesJob::class => 'EST/Family',
        ESTGenerateFastaJob::class => 'EST/FASTA',
        ESTGenerateAccessionJob::class => 'EST/Accession',
        ESTColorSSNJob::class => 'Color SSN',
        ESTNeighborhoodConnectivityJob::class => 'Neighborhood Connectivity',
        ESTClusterAnalysisJob::class => 'Cluster Analysis',
        ESTConvergenceRatioJob::class => 'Convergence Ratio',
        CGFPIdentifyJob::class => 'CGFP/Identify',
        CGFPQuantifyJob::class => 'CGFP/Quantify',
        TaxonomyAccessionJob::class => 'Taxonomy/Accession',
        TaxonomyFamiliesJob::class => 'Taxonomy/Family',
        TaxonomyFastaJob::class => 'Taxonomy/FASTA',
        ESTSSNFinalizationJob::class => 'EST/Analysis',
        GNTGNNJob::class => 'GNN',
        GNTDiagramBlastJob::class => 'GND/BLAST',
        GNTDiagramFastaJob::class => 'GND/FASTA',
        GNTDiagramSequenceIdJob::class => 'GND/ID List',
        GNTViewDiagramJob::class => 'GND/Saved',
    ];

    public function __construct(
        private readonly StateChangeFormValidationService $stateValidationService,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    /**
     * Create the view models representing the list of jobs.
     * @param {array} $jobs - list of Job objects
     * @return {array} list of JobAdminViewModel objects
     */
    public function createJobViewModels(array $jobs): array
    {
        $vms = [];

        /** @var \App\Entity\Job $job */
        foreach ($jobs as $job) {
            $jobType = self::JOB_TYPE_NAME[get_class($job)];

            $vms[] = new JobAdminViewModel(
                type: $jobType,
                id: $job->getId(),
                name: $job->getJobName(),
                timeCreated: $job->getTimeCreated(),
                timeStarted: $job->getTimeStarted(),
                timeCompleted: $job->getTimeCompleted(),
                status: strtoupper($job->getStatus()->value),
                statusBadgeClass: $this->getJobStatusBadge($job->getStatus()),
                userEmail: $job->getUser()->getEmail(),
                userFullName: $job->getUser()->getFullName(),
                userOrganization: $job->getUser()->getPrimaryOrganization()?->getName(),
                viewRoute: $this->getJobViewingRoute($job),
            );
        }

        return $vms;
    }

    private function getJobViewingRoute(Job $job): ?string
    {
        // Only get routes for finished or archvied jobs
        if ($job->getStatus() !== JobStatus::Finished && $job->getStatus() !== JobStatus::Archived) {
            return null;
        }

        $routeName = null;
        if ($job instanceof ESTSSNFinalizationJob) {
            $routeName = ESTResultController::SSN_RESULTS_ROUTE_NAME;
        } else if (is_subclass_of($job, ESTGenerateJob::class)) {
            $routeName = ESTResultController::DATASET_RESULTS_ROUTE_NAME;
        } else if ($job instanceof ESTColorSSNJob || $job instanceof ESTConvergenceRatioJob || $job instanceof ESTNeighborhoodConnectivityJob) {
            $routeName = SSNUtilityController::COLOR_SSN_ROUTE_NAME;
        } else if ($job instanceof ESTClusterAnalysisJob) {
            $routeName = SSNUtilityController::CLUSTER_ANALYSIS_ROUTE_NAME;
        } else if (is_subclass_of($job, TaxonomyJob::class)) {
            $routeName = TaxonomyController::DATASET_RESULTS_ROUTE_NAME;
        } else if ($job instanceof GNTGNNJob) {
            $routeName = GNTController::DATASET_RESULTS_ROUTE_NAME;
        } else if ($job instanceof GNTDiagramJob) {
            $routeName = GNDController::GND_VIEWER_ROUTE_NAME;
        } else if ($job instanceof CGFPIdentifyJob) {
            $routeName = CGFPController::IDENTIFY_ROUTE_NAME;
        } else if ($job instanceof CGFPQuantifyJob) {
            $routeName = CGFPController::QUANTIFY_ROUTE_NAME;
        }

        if ($routeName) {
            $params = [
                'id' => $job->getId(),
                'key' => $job->getUuid(),
            ];
            return $this->urlGenerator->generate($routeName, $params);
        } else {
            return null;
        }
    }

    private function getJobStatusBadge(JobStatus $status): string
    {
        return match ($status) {
            JobStatus::New => 'secondary',
            JobStatus::Running => 'primary',
            JobStatus::Finished => 'success',
            JobStatus::Failed => 'danger',
            JobStatus::Archived => 'info',
            JobStatus::Cancelling => 'info',
            JobStatus::Cancelled => 'info',
        };
    }
}
