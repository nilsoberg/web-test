<?php

namespace App\EST\Controller;

use App\Entity\DisplayName;
use App\Entity\ESTClusterAnalysisJob;
use App\Entity\ESTColorSSNJob;
use App\Entity\ESTConvergenceRatioJob;
use App\Entity\ESTNeighborhoodConnectivityJob;
use App\Entity\Job;
use App\Service\ClusterAnalysisResultFileService;
use App\Service\JobRetrieverService;
use App\Service\JobSummaryService;
use App\Service\ResultFileDownloadService;
use App\ViewModel\Factory\ESTSSNUtilityViewModelFactory;
use App\ViewModel\Factory\ClusterAnalysisResultsViewModelFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/efi-est/results')]
class SSNUtilityController extends AbstractController
{
    public const CLUSTER_ANALYSIS_ROUTE_NAME = 'efi_est_ca_util_results';
    public const COLOR_SSN_ROUTE_NAME = 'efi_est_ssn_util_results';

    public function __construct(
        private readonly JobRetrieverService $jobRetrieverService,
        private readonly LoggerInterface $logger,
        private readonly ResultFileDownloadService $downloadService,
    )
    {
    }

    // Display SSN utility (Color SSN, Neighborhood Connectivity, Convergence Ratio) results page
    #[Route('/ssn-util/{id}/{key}', name: self::COLOR_SSN_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function ssn_util_results(
        int $id,
        Uuid $key,
        ESTSSNUtilityViewModelFactory $viewModelFactory,
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $pageViewModel = $viewModelFactory->create($job);

        return $this->render('efi-est/ssn_utility.html.twig', [
            'page' => $pageViewModel,
            'page_job_id' => $id,
            'is_color_ssn_job' => $job instanceof ESTColorSSNJob,
            'is_neighborhood_connectivity_job' => $job instanceof ESTNeighborhoodConnectivityJob,
            'is_convergence_ratio_job' => $job instanceof ESTConvergenceRatioJob,
            'job_type' => DisplayName::getFrom($job),
        ]);
    }

    // Download generate summary table.  This is used for all SSN utilities
    #[Route('/ssn-util/{id}/{key}/summary', name: 'efi_est_ssn_util_summary', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function summary_table(
        int $id,
        Uuid $key,
        JobSummaryService $summaryService,
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $summaryData = $summaryService->getDownloadSummaryForJob($job);
        $textDownloadResponse = $this->downloadService->createDownloadTextResponse($job, $summaryData, 'color_util_summary');

        return $textDownloadResponse;
    }

    // Download SSN or ancillary files.  Used for all SSN utilities, including Cluster Analysis.
    // For CA, additional files (per-cluster) are downloaded in a separate method below.
    #[Route('/ssn-util/{id}/{key}/file/{fileType}', name: 'efi_est_ssn_util_download', requirements: ['key' => Requirement::UUID_V6, 'fileType' => '[a-zA-Z0-9_]+'], methods: ['GET'])]
    public function ssn_util_download(
        int $id,
        Uuid $key,
        string $fileType,
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $downloadResponse = $this->downloadService->createDownloadResponse($job, $fileType);

        return $downloadResponse;
    }

    // Display Cluster Analysis results page
    #[Route('/ca-util/{id}/{key}', name: self::CLUSTER_ANALYSIS_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function cluster_analysis_results(
        int $id,
        Uuid $key,
        ClusterAnalysisResultsViewModelFactory $viewModelFactory,
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $viewModel = $viewModelFactory->create($job);

        return $this->render('efi-est/results/ssn_utility/cluster_analysis.html.twig', [
            'page' => $viewModel,
            'page_job_id' => $id,
        ]);
    }

    // Download cluster-specific cluster analysis files (e.g. length histogram, etc)
    #[Route('/ca-util/{id}/{key}/file/{requestFileType}/{clusterId}/{seqType}', name: 'efi_est_ca_download', requirements: [
        'key' => Requirement::UUID_V6,
        'requestFileType' => '[a-zA-Z0-9_]+',
        'clusterId' => '[cC]luster[_-]\d+(-\d+)*',
        'seqType' => '(uniprot|uniref50|uniref90)(_domain)?',
    ], methods: ['GET'])]
    public function cluster_analysis_download(
        int $id,
        Uuid $key,
        string $requestFileType,
        string $clusterId,
        Request $request,
        ClusterAnalysisResultFileService $analysisService,
        ?string $seqType = null,
    ): Response
    {
        $useDownloadResponse = $request->query->has('download');

        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job || !$job instanceof ESTClusterAnalysisJob) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        if (!$analysisService->validateCluster($job, $clusterId)) {
            throw new BadRequestHttpException('Invalid cluster ID.');
        }

        $fileType = $analysisService->validateFileType($requestFileType);
        if ($fileType === null) {
            throw new BadRequestHttpException('Invalid file type.');
        }

        $sequenceType = $analysisService->getDomainSequenceType($seqType);
        $filePath = $analysisService->getResultFilePath($job, $clusterId, $fileType, $useDownloadResponse, $sequenceType);
        $extraName = $analysisService->getFilenameExtra($sequenceType);

        if (!file_exists($filePath)) {
            $this->logger->error('Result file missing.', [
                'file_path' => $filePath,
                'job_id' => $job->getId(),
                'file_type' => $fileType->value,
                'cluster_id' => $clusterId,
                'seq_type' => $seqType,
            ]);

            throw new BadRequestHttpException('Requested result file does not exist.');
        }

        $jobName = $job->getJobName() . ' ' . $clusterId;

        if ($useDownloadResponse) {
            $response = $this->downloadService->createDownloadResponseFromPathAndName($filePath, $jobName, $fileType->value, $extraName);
        } else {
            $response = $this->downloadService->createDisplayResponseFromPathAndName($filePath, $jobName, $fileType->value, $extraName);
        }

        return $response;
    }

    // Download consensus residue cluster analysis files
    #[Route('/ca-util/{id}/{key}/cons_res/{residue}/{seqType}', name: 'efi_est_ca_cons_res_download', requirements: [
        'key' => Requirement::UUID_V6,
        'residue' => '[A-Z]',
        'seqType' => '(uniprot|uniref50|uniref90)(_domain)?',
    ], methods: ['GET'])]
    public function consensus_residue_download(
        int $id,
        Uuid $key,
        string $residue,
        Request $request,
        ClusterAnalysisResultFileService $analysisService,
        ?string $seqType = null,
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job || !$job instanceof ESTClusterAnalysisJob) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $sequenceType = $analysisService->getDomainSequenceType($seqType);
        $isDomain = $analysisService->isDomainSequenceType($sequenceType);

        $filePath = $analysisService->getConsensusResidueFilePath($job, $residue, $sequenceType);
        if (!file_exists($filePath)) {
            $this->logger->error('Consensus residue result file missing.', [
                'file_path' => $filePath,
                'job_id' => $job->getId(),
                'residue' => $residue,
                'seqType' => $seqType,
            ]);

            throw new BadRequestHttpException('Requested consensus residue result file does not exist.');
        }

        $jobName = $job->getJobName() . " ConsensusResidue_{$residue}_Position_Summary";
        $extraName = $isDomain ? "Domain" : "Full";

        $response = $this->downloadService->createDownloadResponseFromPathAndName($filePath, $jobName, 'ca_cons_res', $extraName);

        return $response;
    }
}
