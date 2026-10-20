<?php

namespace App\ViewModel\Factory;

use App\Entity\ESTClusterAnalysisJob;
use App\Enum\ClusterAnalysisFileType;
use App\Enum\ClusterAnalysisSequenceType;
use App\Enum\JobStatus;
use App\Enum\SequenceType;
use App\Service\ClusterAnalysisResultFileService;
use App\Service\FormatterService;
use App\Service\JobSummaryService;
use App\Service\ResultFileDownloadService;
use App\Service\ResultFileService;
use App\ViewModel\ClusterAnalysisResultsViewModel;
use App\ViewModel\ClusterAnalysisResults\ConsensusResidueViewModel;
use App\ViewModel\ClusterAnalysisResults\GroupHmmViewModel;
use App\ViewModel\ClusterAnalysisResults\GroupWeblogoViewModel;
use App\ViewModel\ClusterAnalysisResults\HmmViewModel;
use App\ViewModel\ClusterAnalysisResults\JobHmmsViewModel;
use App\ViewModel\ClusterAnalysisResults\JobWeblogosViewModel;
use App\ViewModel\ClusterAnalysisResults\JobLengthHistogramsViewModel;
use App\ViewModel\ClusterAnalysisResults\LengthHistogramViewModel;
use App\ViewModel\ClusterAnalysisResults\WeblogoViewModel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ClusterAnalysisResultsViewModelFactory
{
    public function __construct(
        private readonly ClusterAnalysisResultFileService $analysisService,
        private readonly ESTSSNUtilityViewModelFactory $ssnUtilityViewModelFactory,
        private readonly FormatterService $formatter,
        private readonly JobSummaryService $summaryService,
        private readonly ResultFileDownloadService $downloadService,
        private readonly ResultFileService $fileService,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
	{
    }

    /**
     * Creates a view model containing cluster analysis results, including weblogos, HMMs, length
     * histograms, download links to various files, and a link to the Skylign viewer.
     * @param {ESTClusterAnalysisJob} $job - the cluster analysis job
     * @return {ClusterAnalysisResultsViewModel} view model
     */
    public function create(ESTClusterAnalysisJob $job): ClusterAnalysisResultsViewModel
    {
        $colorSsn = $this->ssnUtilityViewModelFactory->createColorSSNViewModel($job);

        $clusterSizes = $this->analysisService->parseUnirefClusterSizeFile($job);

        $lengthHistograms = $this->getJobLengthHistogramsViewModel($job, $clusterSizes);

        // If the SSN was based on domain sequences, there will be length histograms for one
        // of the following categories.
        $isDomain = array_any($lengthHistograms, function ($vm) {
            return $vm->uniprotDomain !== null
                || $vm->uniref90Domain !== null
                || $vm->uniref50Domain !== null;
        });

        $weblogos = $this->getJobWeblogosViewModel($job, $clusterSizes);
        $hmms = $this->getJobHmmsViewModel($job, $clusterSizes);
        $consRes = $this->getConsensusResidues($job, $isDomain);

        $viewModel = new ClusterAnalysisResultsViewModel(
            jobName: $colorSsn->jobName,
            summaryData: $colorSsn->summaryData,
            summaryDownloadUrl: $colorSsn->summaryDownloadUrl,
            files: $colorSsn->files,
            ssnDownloadRoute: $colorSsn->ssnDownloadRoute,
            displaySsnFileSize: $colorSsn->displaySsnFileSize,
            convergenceRatioRoute: $colorSsn->convergenceRatioRoute,
            cgfpRoute: $colorSsn->cgfpRoute,

            weblogos: $weblogos,
            hmms: $hmms,
            lengthHistograms: $lengthHistograms,
            consensusResidues: $consRes,

            isDomain: $isDomain,
        );

        return $viewModel;
    }

    private function getConsensusResidues(ESTClusterAnalysisJob $job, bool $isDomain): array
    {
        $residuesAsString = $job->getConsensusResidues();
        $residues = explode(',', $residuesAsString);

        $data = [];
        foreach ($residues as $residue) {
            $seqTypes = [false => ClusterAnalysisSequenceType::UniProt];
            if ($isDomain) {
                $seqTypes[true] = ClusterAnalysisSequenceType::UniProtDomain;
            }

            foreach ($seqTypes as $isDomainSubtype => $seqType) {
                $filePath = $this->analysisService->getConsensusResidueFilePath($job, $residue, $seqType);

                if (!file_exists($filePath)) {
                    continue;
                }

                $downloadUrl = $this->urlGenerator->generate('efi_est_ca_cons_res_download', [
                    'id' => $job->getId(),
                    'key' => $job->getUuid(),
                    'residue' => $residue,
                    'seqType' => $this->analysisService->getSequenceTypeValue($seqType),
                ]);

                $downloadSize = $this->formatter->formatSizeUnitsMb(filesize($filePath));

                $residueTitle = $isDomainSubtype ? "$residue (Domain)" : $residue;

                $data[] = new ConsensusResidueViewModel($residueTitle, $downloadUrl, $downloadSize);
            }
        }

        return $data;
    }

    /**
     * Get a single view model encapsulating all weblogos.
     * @param {ESTClusterAnalysisJob} $job - job
     * @param {bool} $isDomain - true if the SSN is based on domains
     * @param {array} $clusterSizes - cluster sizes including UniRef
     * @return {JobWeblogosViewModel} view model containing weblogo data
     */
    private function getJobWeblogosViewModel(ESTClusterAnalysisJob $job, array $clusterSizes): JobWeblogosViewModel
    {
        $fullFiles      = $this->analysisService->getClusterListing($job, ClusterAnalysisFileType::Weblogo, ClusterAnalysisSequenceType::UniProt);
        $domainFiles    = $this->analysisService->getClusterListing($job, ClusterAnalysisFileType::Weblogo, ClusterAnalysisSequenceType::UniProtDomain);

        $collected = [];
        foreach ($fullFiles as $clusterId => $info) {
            if (!isset($clusterSizes[$clusterId]['uniprot'])) {
                continue;
            }
            $full   =                                   $this->getWeblogoViewModel($job, $clusterId, ClusterAnalysisSequenceType::UniProt);
            $domain = isset($domainFiles[$clusterId]) ? $this->getWeblogoViewModel($job, $clusterId, ClusterAnalysisSequenceType::UniProtDomain) : null;
            $clusterName = $this->getClusterName($job, $clusterId, $clusterSizes[$clusterId]['node_cluster_id']);
            $collected[] = new GroupWeblogoViewModel(
                clusterId: $clusterId,
                clusterName: $clusterName,
                numUniprotIds: $clusterSizes[$clusterId]['uniprot'],
                numUniref90Ids: $clusterSizes[$clusterId]['uniref90'] ?? null,
                numUniref50Ids: $clusterSizes[$clusterId]['uniref50'] ?? null,
                full: $full,
                domain: $domain,
            );
        }

        $zipDownloadUrl = $this->urlGenerator->generate('efi_est_ssn_util_download', [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'fileType' => 'ca_weblogo_full_zip',
        ]);

        $zipFilePath = $this->fileService->getResultFilePath($job, 'ca_weblogo_full_zip', true);
        $zipDownloadSize = $this->formatter->formatSizeUnitsMb(filesize($zipFilePath));

        $pimZipDownloadUrl = $this->urlGenerator->generate('efi_est_ssn_util_download', [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'fileType' => 'ca_pim_full_zip',
        ]);

        $pimFilePath = $this->fileService->getResultFilePath($job, 'ca_pim_full_zip', true);
        $pimZipDownloadSize = $this->formatter->formatSizeUnitsMb(filesize($pimFilePath));

        return new JobWeblogosViewModel(
            weblogos: $collected,
            zipDownloadUrl: $zipDownloadUrl,
            zipDownloadSize: $zipDownloadSize,
            pimZipDownloadUrl: $pimZipDownloadUrl,
            pimZipDownloadSize: $pimZipDownloadSize,
        );
    }

    /**
     * Get all of the weblogos and construct view models for each.
     * @param {ESTClusterAnalysisJob} $job - job
     * @param {string} $clusterId - cluster ID as stored on the back end
     * @param {ClusterAnalysisSequenceType} $seqType - one of the enum    
     * @return {WeblogoViewModel[]} array of WeblogoViewModel
     */
    private function getWeblogoViewModel(ESTClusterAnalysisJob $job, string $clusterId, ClusterAnalysisSequenceType $seqType): WeblogoViewModel
    {
        $displayUrl = $this->generateClusterFileDownloadUrl($job, ClusterAnalysisFileType::Weblogo, $clusterId, $seqType);
        $downloadUrl = "$displayUrl?download=1";
        $msaDownloadUrl = $this->generateClusterFileDownloadUrl($job, ClusterAnalysisFileType::Msa, $clusterId, $seqType);
        $msaDownloadUrl = "$msaDownloadUrl?download=1";

        $weblogo = new WeblogoViewModel($displayUrl, $downloadUrl, $msaDownloadUrl);

        return $weblogo;
    }

    /**
     * Get a single view model encapsulating all length histograms.
     * @param {ESTClusterAnalysisJob} $job - job
     * @param {array} $clusterSizes - cluster sizes including UniRef
     * @return {JobWeblogosViewModel[]} view model containing length histogram data
     */
    private function getJobLengthHistogramsViewModel(ESTClusterAnalysisJob $job, array $clusterSizes): array
    {
        $uniprotFiles           = $this->analysisService->getClusterListing($job, ClusterAnalysisFileType::LengthHistogram, ClusterAnalysisSequenceType::UniProt);
        $uniprotDomainFiles     = $this->analysisService->getClusterListing($job, ClusterAnalysisFileType::LengthHistogram, ClusterAnalysisSequenceType::UniProtDomain);
        $uniref90Files          = $this->analysisService->getClusterListing($job, ClusterAnalysisFileType::LengthHistogram, ClusterAnalysisSequenceType::UniRef90);
        $uniref90DomainFiles    = $this->analysisService->getClusterListing($job, ClusterAnalysisFileType::LengthHistogram, ClusterAnalysisSequenceType::UniRef90Domain);
        $uniref50Files          = $this->analysisService->getClusterListing($job, ClusterAnalysisFileType::LengthHistogram, ClusterAnalysisSequenceType::UniRef50);
        $uniref50DomainFiles    = $this->analysisService->getClusterListing($job, ClusterAnalysisFileType::LengthHistogram, ClusterAnalysisSequenceType::UniRef50Domain);

        $collected = [];
        foreach ($uniprotFiles as $clusterId => $info) {
            if (!isset($clusterSizes[$clusterId]['uniprot'])) {
                continue;
            }
            $uniprot        =                                             $this->getLengthHistogramViewModel($job, $clusterId, ClusterAnalysisSequenceType::UniProt);
            $uniprotDomain  = isset($uniprotDomainFiles[$clusterId])    ? $this->getLengthHistogramViewModel($job, $clusterId, ClusterAnalysisSequenceType::UniProtDomain) : null;
            $uniref90       = isset($uniref90Files[$clusterId])         ? $this->getLengthHistogramViewModel($job, $clusterId, ClusterAnalysisSequenceType::UniRef90) : null;
            $uniref90Domain = isset($uniref90DomainFiles[$clusterId])   ? $this->getLengthHistogramViewModel($job, $clusterId, ClusterAnalysisSequenceType::UniRef90Domain) : null;
            $uniref50       = isset($uniref50Files[$clusterId])         ? $this->getLengthHistogramViewModel($job, $clusterId, ClusterAnalysisSequenceType::UniRef50) : null;
            $uniref50Domain = isset($uniref50DomainFiles[$clusterId])   ? $this->getLengthHistogramViewModel($job, $clusterId, ClusterAnalysisSequenceType::UniRef50Domain) : null;

            $clusterName = $this->getClusterName($job, $clusterId, $clusterSizes[$clusterId]['node_cluster_id']);
            $collected[] = new JobLengthHistogramsViewModel(
                clusterId: $clusterId,
                clusterName: $clusterName,
                numUniprotIds: $clusterSizes[$clusterId]['uniprot'] ?? null,
                numUniref90Ids: $clusterSizes[$clusterId]['uniref90'] ?? null,
                numUniref50Ids: $clusterSizes[$clusterId]['uniref50'] ?? null,
                uniprot: $uniprot,
                uniprotDomain: $uniprotDomain,
                uniref90: $uniref90,
                uniref90Domain: $uniref90Domain,
                uniref50: $uniref50,
                uniref50Domain: $uniref50Domain,
            );
        }

        return $collected;
    }

    /**
     * Get all of the length histograms and construct view models for each.
     * @param {ESTClusterAnalysisJob} $job - job
     * @param {string} $clusterId - cluster ID as stored on the back end
     * @param {ClusterAnalysisSequenceType} $seqType - one of the enum
     * @return {LengthHistogramViewModel} length histogram view model
     */
    private function getLengthHistogramViewModel(ESTClusterAnalysisJob $job, string $clusterId, ClusterAnalysisSequenceType $seqType): LengthHistogramViewModel
    {
        $displayUrl = $this->generateClusterFileDownloadUrl($job, ClusterAnalysisFileType::LengthHistogram, $clusterId, $seqType);
        $downloadUrl = "$displayUrl?download=1";
        $histo = new LengthHistogramViewModel($displayUrl, $downloadUrl);
        return $histo;
    }

    /**
     * Get a single view model encapsulating all HMMs.
     * @param {ESTClusterAnalysisJob} $job - job
     * @param {array} $clusterSizes - cluster sizes including UniRef
     * @return {JobWeblogosViewModel} view model containing hmm data
     */
    private function getJobHmmsViewModel(ESTClusterAnalysisJob $job, array $clusterSizes): JobHmmsViewModel
    {
        $fullHmmFiles   = $this->analysisService->getClusterListing($job, ClusterAnalysisFileType::Hmm, ClusterAnalysisSequenceType::UniProt);
        $domainHmmFiles = $this->analysisService->getClusterListing($job, ClusterAnalysisFileType::Hmm, ClusterAnalysisSequenceType::UniProtDomain);

        $collected = [];
        foreach ($fullHmmFiles as $clusterId => $info) {
            if (!isset($clusterSizes[$clusterId]['uniprot'])) {
                continue;
            }
            $fullHmm   =                                        $this->getHmmViewModel($job, $clusterId, ClusterAnalysisSequenceType::UniProt, false);
            $domainHmm = isset($domainHmmFiles[$clusterId]) ?   $this->getHmmViewModel($job, $clusterId, ClusterAnalysisSequenceType::UniProtDomain, true) : null;

            $clusterName = $this->getClusterName($job, $clusterId, $clusterSizes[$clusterId]['node_cluster_id']);
            $collected[] = new GroupHmmViewModel(
                clusterId: $clusterId,
                clusterName: $clusterName,
                numUniprotIds: $clusterSizes[$clusterId]['uniprot'] ?? null,
                numUniref90Ids: $clusterSizes[$clusterId]['uniref90'] ?? null,
                numUniref50Ids: $clusterSizes[$clusterId]['uniref50'] ?? null,
                full: $fullHmm,
                domain: $domainHmm,
            );
        }

        $zipFilePath = $this->fileService->getResultFilePath($job, 'ca_hmm_full_zip', true);
        $zipDownloadSize = $this->formatter->formatSizeUnitsMb(filesize($zipFilePath));

        $zipDownloadUrl = $this->urlGenerator->generate('efi_est_ssn_util_download', [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'fileType' => 'ca_hmm_full_zip',
        ]);

        return new JobHmmsViewModel(
            hmms: $collected,
            zipDownloadUrl: $zipDownloadUrl,
            zipDownloadSize: $zipDownloadSize,
        );
    }

    /**
     * Get all of the HMMs and construct view models for each.
     * @param {ESTClusterAnalysisJob} $job - job
     * @param {string} $clusterId - cluster ID as stored on the back end
     * @param {ClusterAnalysisSequenceType} $seqType - one of the enum    
     * @param {bool} $isDomain - true to retrieve the domain version of the HMM
     * @return {HmmViewModel} view model representing HMM
     */
    private function getHmmViewModel(ESTClusterAnalysisJob $job, string $clusterId, ClusterAnalysisSequenceType $seqType, bool $isDomain): HmmViewModel
    {
        $url = $this->generateClusterFileDownloadUrl($job, ClusterAnalysisFileType::Hmm, $clusterId, $seqType);
        $downloadUrl = "$url?download=1";

        $skylignViewerUrl = $this->urlGenerator->generate('efi_est_ca_skylign_viewer', [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'clusterId' => $clusterId,
            'isDomain' => $isDomain,
        ]);
        $skylignImagePath = null;
        $skylignImageDownloadUrl = null;

        $hmm = new HmmViewModel($skylignImagePath, $skylignImageDownloadUrl, $skylignViewerUrl, $downloadUrl);

        return $hmm;
    }

    /**
     * Generate the URL for downloading the specified file type for the given job and cluster.
     * @param {ESTClusterAnalysisJob} $job - the job
     * @param {ClusterAnalysisFileType} $fileType - the file type
     * @param {string} $clusterId - cluster ID
     * @param {ClusterAnalysisSequenceType} $seqType - one of the enum
     * @return {string} url for downloading
     */
    private function generateClusterFileDownloadUrl(ESTClusterAnalysisJob $job, ClusterAnalysisFileType $fileType, string $clusterId, ClusterAnalysisSequenceType $seqType): string
    {
        $params = [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'requestFileType' => $fileType->value,
            'clusterId' => $clusterId,
        ];
        if ($seqType) {
            $params['seqType'] = $this->analysisService->getSequenceTypeValue($seqType);
        }

        $downloadUrl = $this->urlGenerator->generate('efi_est_ca_download', $params);
        return $downloadUrl;
    }

    /**
     * Gets the cluster name, a viewer-friendly name, from the ID.
     * @param {ESTClusterAnalysisJob} $job - the job
     * @param {string} $clusterId - cluster ID
     * @return {string} cluster name
     */
    private function getClusterName(ESTClusterAnalysisJob $job, string $clusterId, ?string $clusterNodeId): string
    {
        $seqNumName = "Sequence " . preg_replace('/[_-]/', ' ', $clusterId);
        $nodeNumName = "Node " . preg_replace('/[_-]/', ' ', $clusterNodeId ?? $clusterId);
        return "$seqNumName / $nodeNumName";
    }
}
