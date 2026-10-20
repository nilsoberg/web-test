<?php

namespace App\ViewModel\Factory;

use App\CGFP\Controller\CGFPController;
use App\CGFP\Controller\HeatmapController;
use App\Entity\CGFPIdentifyJob;
use App\Entity\CGFPQuantifyJob;
use App\Entity\Job;
use App\Enum\CGFPHeatmapType;
use App\Service\FormatterService;
use App\Service\JobSummaryService;
use App\Service\ResultFileService;
use App\ViewModel\NetworkFileViewModel;
use App\ViewModel\CGFPIdentifyResultsViewModel;
use App\ViewModel\CGFPQuantifyAbundanceViewModel;
use App\ViewModel\CGFPQuantifyHeatmapUrlViewModel;
use App\ViewModel\CGFPQuantifyResultsViewModel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CGFPResultsViewModelFactory
{
    public function __construct(
        private readonly FormatterService $formatter,
        private readonly JobSummaryService $summaryService,
        private readonly ResultFileService $fileService,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
	{
    }

    public function createIdentify(CGFPIdentifyJob $job): ?CGFPIdentifyResultsViewModel
    {
        $summaryData = $this->summaryService->getDisplaySummaryForJob($job);

        $ssn = $this->getDownloadRoute($job, 'identify_ssn');
        $cdhit = $this->getDownloadRoute($job, 'identify_cdhit');
        $markers = $this->getDownloadRoute($job, 'identify_markers');

        $summaryDownloadUrl = $this->urlGenerator->generate(CGFPController::IDENTIFY_DATASET_DOWNLOAD_ROUTE_NAME, [
            'id' => $job->getId(),
            'key' => $job->getUuid()
        ]);

        $viewModel = new CGFPIdentifyResultsViewModel(
            jobName: $job->getJobName() ?? '',
            summaryData: $summaryData,
            summaryDownloadUrl: $summaryDownloadUrl,
            id: $job->getId(),
            key: $job->getUuid(),
            ssn: $ssn,
            cdhitDownload: $cdhit,
            markerDownload: $markers,
        );

        return $viewModel;
    }

    public function createQuantify(CGFPQuantifyJob $job): ?CGFPQuantifyResultsViewModel
    {
        $summaryData = $this->summaryService->getDisplaySummaryForJob($job);

        $ssn = $this->getDownloadRoute($job, 'quantify_ssn');
        $cdhit = $this->getDownloadRoute($job, 'identify_cdhit');
        $markers = $this->getDownloadRoute($job, 'identify_markers');
        $desc = $this->getDownloadRoute($job, 'quantify_desc');

        $abundanceFiles = $this->getAbundanceFiles($job);

        $clusterHeatmapUrl = $this->getHeatmapUrl($job, CGFPHeatmapType::Cluster, true);
        $singletonHeatmapUrl = $this->getHeatmapUrl($job, CGFPHeatmapType::Singleton, true);
        $mergedHeatmapUrl = $this->getHeatmapUrl($job, CGFPHeatmapType::Merged, true);

        $clusterBoxplotUrl = $this->getHeatmapUrl($job, CGFPHeatmapType::Cluster, false);
        $singletonBoxplotUrl = $this->getHeatmapUrl($job, CGFPHeatmapType::Singleton, false);
        $mergedBoxplotUrl = $this->getHeatmapUrl($job, CGFPHeatmapType::Merged, false);

        $summaryDownloadUrl = $this->urlGenerator->generate(CGFPController::QUANTIFY_DATASET_DOWNLOAD_ROUTE_NAME, [
            'id' => $job->getId(),
            'key' => $job->getUuid()
        ]);

        $viewModel = new CGFPQuantifyResultsViewModel(
            jobName: $job->getJobName() ?? '',
            summaryData: $summaryData,
            summaryDownloadUrl: $summaryDownloadUrl,
            id: $job->getId(),
            key: $job->getUuid(),
            ssn: $ssn,
            cdhitDownload: $cdhit,
            markerDownload: $markers,
            metagenomeDesc: $desc,
            abundanceFiles: $abundanceFiles,
            clusterHeatmapUrl: $clusterHeatmapUrl,
            singletonHeatmapUrl: $singletonHeatmapUrl,
            mergedHeatmapUrl: $mergedHeatmapUrl,
            clusterBoxplotUrl: $clusterBoxplotUrl,
            singletonBoxplotUrl: $singletonBoxplotUrl,
            mergedBoxplotUrl: $mergedBoxplotUrl,
        );

        return $viewModel;
    }

    private function getHeatmapUrl(CGFPQuantifyJob $job, CGFPHeatmapType $heatmapType, bool $forHeatmap): string
    {
        $routeName = $forHeatmap ? HeatmapController::HEATMAP_ROUTE_NAME : HeatmapController::BOXPLOT_ROUTE_NAME;
        return $this->urlGenerator->generate($routeName, [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'type' => $heatmapType->value,
        ]);
    }

    public function createHeatmap(CGFPQuantifyJob $job, CGFPIdentifyJob $identifyJob, CGFPHeatmapType $heatmapType): CGFPQuantifyHeatmapUrlViewModel
    {
        return new CGFPQuantifyHeatmapUrlViewModel(
            id: $job->getId(),
            key: $job->getUuid(),
            identifyId: $identifyJob->getId(),
            jobName: $job->getJobName(),
            heatmapType: $heatmapType->value,
            searchType: $job->getQuantifySearchType()->value,
            refDb: $identifyJob->getUnirefVersion()->value,
            searchSens: 'normal',
            cdhitSequenceIdentity: $identifyJob->getCdhitSequenceIdentity(),
            width: \App\Config\Constants::DEFAULT_HEATMAP_WIDTH,
        );
    }

    public function createBoxplot(CGFPQuantifyJob $job, CGFPIdentifyJob $identifyJob, CGFPHeatmapType $heatmapType): CGFPQuantifyHeatmapUrlViewModel
    {
        return new CGFPQuantifyHeatmapUrlViewModel(
            id: $job->getId(),
            key: $job->getUuid(),
            identifyId: $identifyJob->getId(),
            jobName: $job->getJobName(),
            heatmapType: $heatmapType->value,
        );
    }

    /**
     * Get the quantified abundance text file paths for the quantify job.
     * @param {CGFPQuantifyJob} $job
     * @return {CGFPQuantifyAbundanceViewModel}
     */
    private function getAbundanceFiles(CGFPQuantifyJob $job): CGFPQuantifyAbundanceViewModel
    {
        return new CGFPQuantifyAbundanceViewModel(
            proteinMedian: $this->getDownloadRoute($job, 'quantify_protein_abundance_median'),
            clusterMedian: $this->getDownloadRoute($job, 'quantify_cluster_abundance_median'),
            proteinNormMedian: $this->getDownloadRoute($job, 'quantify_protein_abundance_genome_normalized_median'),
            clusterNormMedian: $this->getDownloadRoute($job, 'quantify_cluster_abundance_genome_normalized_median'),
            proteinMean: $this->getDownloadRoute($job, 'quantify_protein_abundance_mean'),
            clusterMean: $this->getDownloadRoute($job, 'quantify_cluster_abundance_mean'),
            proteinNormMean: $this->getDownloadRoute($job, 'quantify_protein_abundance_genome_normalized_mean'),
            clusterNormMean: $this->getDownloadRoute($job, 'quantify_cluster_abundance_genome_normalized_mean'),
        );
    }

    private function getDownloadRoute(Job $job, string $fileTypeAlias): ?NetworkFileViewModel
    {
        $filePath = $this->fileService->getResultFilePath($job, $fileTypeAlias, validateExistence: false);

        if (!file_exists($filePath)) {
            return null;
        }

        $size = filesize($filePath);
        $fileSize = $this->formatter->formatSizeUnitsMb($size);

        $downloadRoute = $this->generateUrl($job, $fileTypeAlias);

        $ssnGnn = new NetworkFileViewModel(
            name: $fileTypeAlias, // Required in the view module but not used in the template
            size: $fileSize,
            route: $downloadRoute
        );

        return $ssnGnn;
    }

    private function generateUrl(Job $job, string $fileType): string
    {
        $url = $this->urlGenerator->generate(CGFPController::DOWNLOAD_ROUTE_NAME, [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'fileType' => $fileType,
        ]);
        return $url;
    }
}
