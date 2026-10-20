<?php

namespace App\ViewModel\Factory;

use App\Entity\ESTColorSSNJob;
use App\Entity\ESTConvergenceRatioJob;
use App\Entity\ESTNeighborhoodConnectivityJob;
use App\Entity\Job;
use App\Service\FormatterService;
use App\Service\JobSummaryService;
use App\Service\ResultFileDownloadService;
use App\Service\ResultFileService;
use App\ViewModel\ESTColorSSNViewModel;
use App\ViewModel\ESTConvergenceRatioViewModel;
use App\ViewModel\ESTNeighborhoodConnectivityViewModel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ESTSSNUtilityViewModelFactory 
{
    public function __construct(
        private readonly FormatterService $formatter,
        private readonly JobSummaryService $summaryService,
        private readonly ResultFileDownloadService $downloadService,
        private readonly ResultFileService $fileService,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
	{
    }

    /**
     * Creates a view model for one of the SSN utility jobs.
     * @param {Job} $job - the SSN utility job entity
     * @returns {object} one of ESTColorSSNViewModel, ESTConvergenceRatioViewModel, or ESTNeighborhoodConnectivityViewModel
     */
    public function create(Job $job): object
    {
        if ($job instanceof ESTColorSSNJob) {
            return $this->createColorSSNViewModel($job);
        } else if ($job instanceof ESTNeighborhoodConnectivityJob) {
            return $this->createNeighborhoodConnectivityViewModel($job);
        } else if ($job instanceof ESTConvergenceRatioJob) {
            return $this->createConvergenceRatioViewModel($job);
        } else {
            throw new \LogicException('Invalid Job entity for SSN utility');
        }

        return $viewModel;
    }

    /**
     * Creates a view model representing a Color SSN utility job.
     * @param {Job} $job - the job entity; support ESTClusterAnalysisJob and ESTColorSSNJob
     * @returns {ESTColorSSNViewModel} view model
     */
    public function createColorSSNViewModel(Job $job): ESTColorSSNViewModel
    {
        $summaryData = $this->summaryService->getDisplaySummaryForJob($job);
        $summaryDownloadUrl = $this->urlGenerator->generate('efi_est_ssn_util_summary', [
            'id' => $job->getId(),
            'key' => $job->getUuid()
        ]);

        //TODO: get conv ratio route
        //TODO: get cgfp route
        $ssnDownloadRoute = $this->generateUrl($job, 'color_util_ssn');
        $files = $this->getColorSsnFiles($job);
        $ssnFileSize = $this->getSsnFileSize($job, 'color_util_ssn');
        $viewModel = new ESTColorSSNViewModel(
            jobName: $job->getUploadedFilename(),
            summaryData: $summaryData,
            summaryDownloadUrl: $summaryDownloadUrl,
            files: $files,
            ssnDownloadRoute: $ssnDownloadRoute,
            displaySsnFileSize: $ssnFileSize,
            convergenceRatioRoute: null,
            cgfpRoute: null,
        );
        return $viewModel;
    }

    private function createConvergenceRatioViewModel(ESTConvergenceRatioJob $job): ESTConvergenceRatioViewModel
    {
        $summaryData = $this->summaryService->getDisplaySummaryForJob($job);
        $summaryDownloadUrl = $this->urlGenerator->generate('efi_est_ssn_util_summary', [
            'id' => $job->getId(),
            'key' => $job->getUuid()
        ]);

        $files = $this->getConvRatioFiles($job);
        $viewModel = new ESTConvergenceRatioViewModel(
            jobName: $job->getUploadedFilename(),
            summaryData: $summaryData,
            summaryDownloadUrl: $summaryDownloadUrl,
            files: $files,
        );
        return $viewModel;
    }

    private function createNeighborhoodConnectivityViewModel(ESTNeighborhoodConnectivityJob $job): ESTNeighborhoodConnectivityViewModel
    {
        $summaryData = $this->summaryService->getDisplaySummaryForJob($job);
        $summaryDownloadUrl = $this->urlGenerator->generate('efi_est_ssn_util_summary', [
            'id' => $job->getId(),
            'key' => $job->getUuid()
        ]);

        $ssnDownloadRoute = $this->generateUrl($job, 'nb_conn_ssn');
        $files = $this->getNbConnFiles($job);
        $ssnFileSize = $this->getSsnFileSize($job, 'nb_conn_ssn');
        $viewModel = new ESTNeighborhoodConnectivityViewModel(
            jobName: $job->getUploadedFilename(),
            summaryData: $summaryData,
            summaryDownloadUrl: $summaryDownloadUrl,
            files: $files,
            ssnDownloadRoute: $ssnDownloadRoute,
            displaySsnFileSize: $ssnFileSize,
        );
        return $viewModel;
    }

    private function getConvRatioFiles(ESTConvergenceRatioJob $job): array
    {
        $convRatioTableRoute = $this->generateUrl($job, 'conv_ratio_table');

        $files = [];

        $files[] = [
            'files' => [
                ['route' => $convRatioTableRoute, 'description' => 'Convergence Ratio Table'],
            ]
        ];

        return $files;
    }

    private function getNbConnFiles(ESTNeighborhoodConnectivityJob $job): array
    {
        $ncScaleRoute = $this->generateUrl($job, 'nb_conn_nc_scale');
        $ncTableRoute = $this->generateUrl($job, 'nb_conn_nc_table');

        $files = [];

        $files[] = [
            'files' => [
                ['route' => $ncScaleRoute, 'description' => 'Neighborhood Connectivity Color Scale (PNG)'],
                ['route' => $ncTableRoute, 'description' => 'Neighborhood Connectivity Table'],
            ]
        ];

        return $files;
    }

    private function getSsnFileSize(Job $job, string $fileTypeAlias): string
    {
        $filePath = $this->fileService->getResultFilePath($job, $fileTypeAlias);

        $size = 0;
        if (file_exists($filePath)) {
            $size = filesize($filePath);
        }

        return $this->formatter->formatSizeUnitsMbGb($size);
    }

    private function getColorSsnFiles(Job $job): array
    {
        $clusterNumMapRoute = $this->generateUrl($job, 'color_util_cluster_num_map');
        $clusterSizesRoute = $this->generateUrl($job, 'color_util_cluster_sizes');
        $convRatioRoute = $this->generateUrl($job, 'color_util_conv_ratio');
        $swissProtClustersRoute = $this->generateUrl($job, 'color_util_swissprot_clusters');
        $uniprotFastaRoute = $this->generateUrl($job, 'color_util_uniprot_fasta');
        $uniref50FastaRoute = $this->generateUrl($job, 'color_util_uniref50_fasta');
        $uniref90FastaRoute = $this->generateUrl($job, 'color_util_uniref90_fasta');
        $uniprotIdsRoute = $this->generateUrl($job, 'color_util_uniprot_ids');
        $uniref50IdsRoute = $this->generateUrl($job, 'color_util_uniref50_ids');
        $uniref90IdsRoute = $this->generateUrl($job, 'color_util_uniref90_ids');

        $idFastaFiles = [];

        $idFastaFiles[] = ['route' => $uniprotIdsRoute, 'description' => 'UniProt ID lists per cluster'];
        $idsUniref90File = $this->fileService->getResultFilePath($job, 'color_util_uniref90_ids', validateExistence: false);
        if (file_exists($idsUniref90File)) {
            $idFastaFiles[] = ['route' => $uniref90IdsRoute, 'description' => 'UniRef90 ID lists per cluster'];
        }
        $idsUniref50File = $this->fileService->getResultFilePath($job, 'color_util_uniref50_ids', validateExistence: false);
        if (file_exists($idsUniref50File)) {
            $idFastaFiles[] = ['route' => $uniref50IdsRoute, 'description' => 'UniRef50 ID lists per cluster'];
        }

        $idFastaFiles[] = ['route' => $uniprotFastaRoute, 'description' => 'UniProt FASTA files per cluster'];
        $uniref90FastaFile = $this->fileService->getResultFilePath($job, 'color_util_uniref90_fasta', validateExistence: false);
        if (file_exists($uniref90FastaFile)) {
            $idFastaFiles[] = ['route' => $uniref90FastaRoute, 'description' => 'UniRef90 FASTA files per cluster'];
        }
        $uniref50FastaFile = $this->fileService->getResultFilePath($job, 'color_util_uniref50_fasta', validateExistence: false);
        if (file_exists($uniref50FastaFile)) {
            $idFastaFiles[] = ['route' => $uniref50FastaRoute, 'description' => 'UniRef50 FASTA files per cluster'];
        }

        $files = [];

        $files[] = [
            'title' => 'Mapping Tables',
            'files' => [
                ['route' => $clusterNumMapRoute, 'description' => 'UniProt ID-Color-Cluster number mapping table'],
            ]
        ];
        $files[] = [
            'title' => 'ID Lists and FASTA Files per Cluster',
            'files' => $idFastaFiles,
        ];
        $files[] = [
            'title' => 'Miscellaneous Files',
            'files' => [
                ['route' => $clusterSizesRoute, 'description' => 'Cluster sizes'],
                ['route' => $convRatioRoute, 'description' => 'Cluster-based convergence ratio for UniProt IDs'],
                ['route' => $swissProtClustersRoute, 'description' => 'SwissProt annotations by cluster'],
            ]
        ];

        return $files;
    }

    private function generateUrl(Job $job, string $fileType): string
    {
        $url = $this->urlGenerator->generate('efi_est_ssn_util_download', [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'fileType' => $fileType,
        ]);
        return $url;
    }
}
