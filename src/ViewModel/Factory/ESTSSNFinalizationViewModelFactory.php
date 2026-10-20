<?php

namespace App\ViewModel\Factory;

use App\Entity\DisplayName;
use App\Entity\ESTSSNFinalizationJob;
use App\Entity\Job;
use App\Enum\SequenceType;
use App\EST\Controller\ESTResultController;
use App\Service\FormatterService;
use App\Service\JobSummaryService;
use App\Service\ResultFileService;
use App\Service\SSNResultStatsService;
use App\Service\SunburstDescriptionService;
use App\ViewModel\NetworkFileViewModel;
use App\ViewModel\ESTSSNFinalizationViewModel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ESTSSNFinalizationViewModelFactory
{
    // Inject the project's data directory path via services.yaml
    public function __construct(
        private readonly FormatterService $formatter,
        private readonly JobSummaryService $summaryService,
        private readonly ResultFileService $fileService,
        private readonly SSNResultStatsService $statsService,
        private readonly SunburstDescriptionService $sunburstDescriptionService,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    /**
     * The main public method to create the list of view models.
     *
     * @return ESTSSNFinalizationViewModel
     */
    public function create(ESTSSNFinalizationJob $job): ESTSSNFinalizationViewModel
    {
        $parentJob = $job->getParentJob();
        $parentSummaryData = $this->summaryService->getDisplaySummaryForJob($parentJob);
        $summaryData = $this->summaryService->getDisplaySummaryForJob($job);

        $resultDir = $this->fileService->getResultsDirectory($job);
        $stats = $this->statsService->loadStats($job);
        $sunburstParams = $this->getSunburstParams($job);
        $repnodeNetworks = [];
        $fullNetwork = null;

        $computeNc = $job->isComputeNeighborhoodConnectivity();

        $fileIndex = 0;
        foreach ($stats as $alias => $fileInfo) {
            $route = $this->urlGenerator->generate(ESTResultController::FILE_ROUTE_NAME, [
                'id' => $job->getId(),
                'key' => $job->getUuid(),
                'fileType' => $alias,
            ]);

            $ncScaleRouteName = $computeNc ? $this->getNcScaleRoute($job, $fileInfo) : null;

            $fileModel = new NetworkFileViewModel(
                name: $fileInfo['name'],
                index: $fileIndex,
                percentId: $fileInfo['percentage'] . '%',
                nodes: $fileInfo['nodes'] ?? 'N/A',
                edges: $fileInfo['edges'] ?? 'N/A',
                size: isset($fileInfo['size']) ? $this->formatter->formatSizeUnitsMbGb($fileInfo['size']) : 'N/A',
                path: sprintf('%s/%s', $resultDir, $fileInfo['name']),
                alias: $alias,
                route: $route,
                transferId: $fileInfo['type'],
                ncScaleRoute: $ncScaleRouteName,
            );

            if ($fileInfo['type'] === 'full') {
                $fullNetwork = $fileModel;
            } else {
                $repnodeNetworks[] = $fileModel;
            }
        }

        $summaryDownloadUrl = $this->urlGenerator->generate('efi_est_analysis_summary', [
            'id' => $job->getId(),
            'key' => $job->getUuid()
        ]);
        $parentSummaryDownloadUrl = $this->urlGenerator->generate('efi_est_dataset_summary', [
            'id' => $parentJob->getId(),
            'key' => $parentJob->getUuid()
        ]);

        $viewModel = new ESTSSNFinalizationViewModel(
            repnodeNetworks: $repnodeNetworks,
            fullNetwork: $fullNetwork,
            parentSummaryData: $parentSummaryData,
            parentSummaryDownloadUrl: $parentSummaryDownloadUrl,
            summaryData: $summaryData,
            summaryDownloadUrl: $summaryDownloadUrl,
            id: $job->getId(),
            key: $job->getUuid(),
            jobName: $job->getJobName(),
            sunburstParams: $sunburstParams,
            parentJobType: DisplayName::getFrom($parentJob),
        );

        return $viewModel;
    }

    private function getNcScaleRoute(ESTSSNFinalizationJob $job, array $fileInfo): string
    {
        if ($fileInfo['type'] === 'full') {
            $fileType = 'full_ssn_nc_scale';
        } else {
            $fileType = 'repnode_' . $fileInfo['percentage'] . '_nc_scale';
        }

        $ncScaleRoute = $this->urlGenerator->generate(ESTResultController::FILE_ROUTE_NAME, [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'fileType' => $fileType,
        ]);

        return $ncScaleRoute;
    }

    private function getSunburstParams(ESTSSNFinalizationJob $job): array
    {
        $parentJob = $job->getParentJob();

        $sunburstDescText = $this->sunburstDescriptionService->getSunburstDescription($parentJob);

        $primaryIdType = $parentJob->getUnirefVersion() ?? SequenceType::UniProt;

        // Check if the analysis job has a sunburst file; if so, use that file, otherwise use
        // the parent sunburst dataset
        $dataFile = $this->fileService->getResultFilePath($job, 'sunburst_json', validateExistence: false);
        if ($dataFile && file_exists($dataFile)) {
            $sunburstJob = $job;
        } else {
            $sunburstJob = $parentJob;
        }

        $sunburstParams = $this->sunburstDescriptionService->getSunburstParameters(
            job: $sunburstJob,
            dataUrlRoute: 'efi_est_sunburst_data',
            fastaUrlRoute: 'efi_est_sunburst_fasta',
            sunburstDescText: $sunburstDescText,
            idType: $primaryIdType,
        );

        return $sunburstParams;
    }
}
