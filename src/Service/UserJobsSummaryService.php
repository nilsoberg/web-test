<?php

namespace App\Service;

use App\Entity\CGFPIdentifyJob;
use App\Entity\CGFPQuantifyJob;
use App\Entity\ESTClusterAnalysisJob;
use App\Entity\ESTColorSSNJob;
use App\Entity\ESTConvergenceRatioJob;
use App\Entity\ESTGenerateJob;
use App\Entity\ESTNeighborhoodConnectivityJob;
use App\Entity\ESTSSNFinalizationJob;
use App\Entity\GNTDiagramJob;
use App\Entity\GNTGNNJob;
use App\Entity\Job;
use App\Entity\TaxonomyJob;
use App\Enum\JobStatus;
use App\Enum\JobSummarySortOrder;
use App\Enum\SummaryJobType;
use App\CGFP\Controller\CGFPController;
use App\EST\Controller\ESTResultController;
use App\EST\Controller\SSNUtilityController;
use App\GNT\Controller\GNDController;
use App\GNT\Controller\GNTController;
use App\Repository\JobRepository;
use App\Service\JobMetadataService;
use App\Taxonomy\Controller\TaxonomyController;
use App\ViewModel\JobViewModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserJobsSummaryService
{
    const DEFAULT_COLOR = '';

    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly Security $security,
        private readonly JobMetadataService $jobMetadata,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly array $jobTypeColors,
    )
    {
    }

    /**
     * Get array of Job view models for display in Previous Jobs/Job Dashboard.
     * @return JobViewModel[]
     */
    public function getJobsForDisplay(SummaryJobType $summaryType, JobSummarySortOrder $sortBy = JobSummarySortOrder::DateCompleted, bool $sortAsc = false): array
    {
        $currentUser = $this->security->getUser();

        if (!$currentUser) {
            return [];
        }

        // Get all of the jobs associated with the user and summary type
        $jobList = $this->jobRepository->getSummaryJobs($currentUser, $summaryType, $sortBy, $sortAsc);

        // Convert the job list into a tree format.  For example, if the summary type is EST,
        // SSNFinalization jobs should be children of ESTGenerate jobs, and likewise, color SSN
        // jobs can be children of SSNFinalization jobs
        $modelTree = $this->buildTree($jobList);

        foreach ($modelTree as $vm) {
            $this->addJobMetadata($vm, $summaryType);
        }

        return $modelTree;
    }

    /**
     * Add metadata to the job object
     */
    private function addJobMetadata(JobViewModel $vm, SummaryJobType $summaryType): void
    {
        $vm->prettyStatus = $this->getStatusString($vm->job->getStatus());

        if ($vm->job->getStatus() === JobStatus::Finished) {
            $routeName = $this->getRouteName($vm->job);
            if ($routeName) {
                $url = $this->urlGenerator->generate($routeName, ['id' => $vm->job->getId(), 'key' => $vm->job->getUuid()]);
                $vm->route = $url;
            }
            $vm->color = $this->getJobColor($vm->job);
        }

        $data = $this->jobMetadata->collectMetadata($vm->job);

        if (count($vm->children) > 0) {
            foreach ($vm->children as $childModel) {
                $this->addJobMetadata($childModel, $summaryType);
            }
        }

        $vm->metadata = $data;
    }

    private function getJobColor(Job $job): ?string
    {
        if ($job instanceof ESTSSNFinalizationJob) {
            return $this->jobTypeColors['analysis'] ?? self::DEFAULT_COLOR;
        } else if (is_subclass_of($job, ESTGenerateJob::class)) {
            return $this->jobTypeColors['est'] ?? self::DEFAULT_COLOR;
        } else if ($job instanceof ESTColorSSNJob || $job instanceof ESTConvergenceRatioJob || $job instanceof ESTNeighborhoodConnectivityJob || $job instanceof ESTClusterAnalysisJob) {
            return $this->jobTypeColors['colorssn'] ?? self::DEFAULT_COLOR;
        } else if (is_subclass_of($job, TaxonomyJob::class)) {
            return $this->jobTypeColors['taxonomy'] ?? self::DEFAULT_COLOR;
        } else if ($job instanceof GNTGNNJob) {
            return $this->jobTypeColors['gnt'] ?? self::DEFAULT_COLOR;
        } else if ($job instanceof GNTDiagramJob) {
            return $this->jobTypeColors['gnd'] ?? self::DEFAULT_COLOR;
        } else if ($job instanceof CGFPIdentifyJob || $job instanceof CGFPQuantifyJob) {
            return $this->jobTypeColors['cgfp'] ?? self::DEFAULT_COLOR;
        } else {
            return self::DEFAULT_COLOR;
        }
    }

    private function getRouteName(Job $job): ?string
    {
        if ($job instanceof ESTSSNFinalizationJob) {
            return ESTResultController::SSN_RESULTS_ROUTE_NAME;
        } else if (is_subclass_of($job, ESTGenerateJob::class)) {
            return ESTResultController::DATASET_RESULTS_ROUTE_NAME;
        } else if ($job instanceof ESTColorSSNJob || $job instanceof ESTConvergenceRatioJob || $job instanceof ESTNeighborhoodConnectivityJob) {
            return SSNUtilityController::COLOR_SSN_ROUTE_NAME;
        } else if ($job instanceof ESTClusterAnalysisJob) {
            return SSNUtilityController::CLUSTER_ANALYSIS_ROUTE_NAME;
        } else if (is_subclass_of($job, TaxonomyJob::class)) {
            return TaxonomyController::DATASET_RESULTS_ROUTE_NAME;
        } else if ($job instanceof GNTGNNJob) {
            return GNTController::DATASET_RESULTS_ROUTE_NAME;
        } else if ($job instanceof GNTDiagramJob) {
            return GNDController::GND_VIEWER_ROUTE_NAME;
        } else if ($job instanceof CGFPIdentifyJob) {
            return CGFPController::IDENTIFY_ROUTE_NAME;
        } else if ($job instanceof CGFPQuantifyJob) {
            return CGFPController::QUANTIFY_ROUTE_NAME;
        } else {
            return null;
        }
    }

    private function getJobName(Job $job): ?string
    {
        if ($job instanceof ESTColorSSNJob || $job instanceof ESTConvergenceRatioJob || $job instanceof ESTNeighborhoodConnectivityJob || $job instanceof ESTClusterAnalysisJob || $job instanceof GNTGNNJob) {
            return $job->getUploadedFilename();
        } else {
            return $job->getJobName();
        }
    }

    /**
     * Takes a flat array of Job entities and organizes them into a hierarchical tree.
     *
     * @param Job[] $jobs The flat list of jobs from the database.
     * @return JobViewModel[] The list of root-level jobs, with children nested inside.
     */
    private function buildTree(array $jobs): array
    {
        $jobsById = [];
        $rootJobs = [];
        $models = [];

        // First pass: Index all jobs by their ID, create a view model to represent the
        // Job, and initialize a 'children' property.
        foreach ($jobs as $job) {
            $vm = new JobViewModel($job);
            $vm->jobName = $this->getJobName($job);
            $this->assignJobBadgeAndAction($vm);
            $jobsById[$job->getId()] = $vm;
            $vm->children = [];
            $models[] = $vm;
        }

        // Second pass: Link children to their parents.
        foreach ($models as $vm) {
            $parent = $vm->job->getParentJob();

            if ($parent !== null && isset($jobsById[$parent->getId()])) {
                // This job has a parent, so add it to the parent's children array.
                $jobsById[$parent->getId()]->children[] = $vm;
            } else {
                // This is a root job (no parent), so add it to the root list.
                $rootJobs[] = $vm;
            }
        }

        return $rootJobs;
    }

    private function assignJobBadgeAndAction(JobViewModel $vm)
    {
        $action = '';
        if ($vm->job->getStatus() === JobStatus::Failed) {
            $vm->badgeStatus = 'failed';
            $vm->badgeIcon = 'exclamation-triangle-fill';
            $vm->actionIcon = 'trash-fill';
            $vm->actionTooltip = 'Delete';
            $vm->actionStyle = 'btn-action btn-delete';
            $action = 'archive';
        } else if ($vm->job->getStatus() === JobStatus::Finished) {
            $vm->badgeStatus = 'finished';
            $vm->badgeIcon = 'check-square';
            $vm->actionIcon = 'recycle';
            $vm->actionTooltip = 'Archive';
            $vm->actionStyle = 'btn-action btn-delete';
            $action = 'archive';
        } else if ($vm->job->getStatus() === JobStatus::Running) {
            $vm->badgeStatus = 'running';
            $vm->badgeIcon = 'hourglass-split hourglass-flip';
            $vm->actionIcon = 'x-square';
            $vm->actionTooltip = 'Cancel';
            $vm->actionStyle = 'btn-action';
            $action = 'cancel';
        } else {
            $vm->badgeStatus = 'new';
            $vm->badgeIcon = 'clock-fill';
            $vm->actionIcon = 'x-square';
            $vm->actionTooltip = 'Cancel';
            $vm->actionStyle = 'btn-action';
            $action = 'archive';
        }

        if ($action === 'archive') {
            $vm->actionUrl = $this->urlGenerator->generate('app_job_action_archive', ['id' => $vm->job->getId(), 'key' => $vm->job->getUuid()]);
        } else if ($action === 'cancel') {
            $vm->actionUrl = $this->urlGenerator->generate('app_job_action_cancel', ['id' => $vm->job->getId(), 'key' => $vm->job->getUuid()]);
        } else {
            $vm->actionUrl = '';
        }
    }

    private function getStatusString(JobStatus $status): string
    {
        $value = "";
        switch ($status) {
            case JobStatus::New:
                $value = "NEW";
                break;
            case JobStatus::Running:
                $value = "RUNNING";
                break;
            case JobStatus::Finished:
                $value = "FINISHED";
                break;
            case JobStatus::Failed:
                $value = "FAILED";
                break;
            case JobStatus::Archived:
                $value = "ARCHIVED";
                break;
            case JobStatus::Cancelling:
                $value = "CANCELLED";
                break;
        }
        return $value;
    }
}
