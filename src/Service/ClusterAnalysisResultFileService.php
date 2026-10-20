<?php

namespace App\Service;

use App\Entity\ESTClusterAnalysisJob;
use App\Enum\ClusterAnalysisFileType;
use App\Enum\ClusterAnalysisSequenceType;
use App\Enum\SequenceType;
use App\Service\ResultFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ClusterAnalysisResultFileService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ResultFileService $fileService,
    )
    {
    }


    /**
     * Cluster analysis results include files similar to the color SSN output.  A result directory
     * additionally contains results for individual clusters, such as MSAs, weblogos, length
     * histograms, and HMMs.  Results are stored in the following structure:
     *
     *   RESULTS_DIR/               - main color SSN results, fasta zip files, etc
     *       data/
     *          msa/{seq_type}      - multiple sequence alignments, one per cluster and sequence type
     *          hmms/{seq_type}     - HMMs, one per cluster and sequence type
     *          weblogos/{seq_type} - Weblogos, one per cluster and sequence type
     *          cons_res/{seq_type} - consensus residues results; one per residue and sequence type
     *
     * {seq_type} refers to the type of the sequence (i.e. uniprot, uniref90, or uniref50).
     * Results for a UniRef90 input network will include results for UniProt and UniRef90, and for
     * UniRef50 input, UniProt and UniRef50 will be included.  If the input network is based on
     * domain sequences, then results will be provided for the domain length sequence as well as
     * the full length sequence.  Consensus residue results are provided per-residue not cluster.
     */

    /**
     * Verify that the file type provided is supported; used to validate input parameters
     * in a controller.
     * @param {string} $fileType - should match one of the backing values of ClusterAnalysisFileType enum
     * @returns {ClusterAnalysisFileType} returns one of the enum values if valid, otherwise null
     */
    public function validateFileType(string $fileType): ?ClusterAnalysisFileType
    {
        return match($fileType) {
            ClusterAnalysisFileType::ConsensusResidue->value => ClusterAnalysisFileType::ConsensusResidue,
            ClusterAnalysisFileType::Hmm->value => ClusterAnalysisFileType::Hmm,
            ClusterAnalysisFileType::LengthHistogram->value => ClusterAnalysisFileType::LengthHistogram,
            ClusterAnalysisFileType::Msa->value => ClusterAnalysisFileType::Msa,
            ClusterAnalysisFileType::Weblogo->value => ClusterAnalysisFileType::Weblogo,
            default => null
        };
    }

    /**
     * Verify that the specific cluster exists in the dataset.
     * @param {string} $clusterId - cluster identifier
     * @returns {bool} true if the cluster exists, false otherwise
     */
    public function validateCluster(ESTClusterAnalysisJob $job, string $clusterId): bool
    {
        if (!preg_match('/^[cC]luster[_-]\d+(-\d+)*$/', $clusterId)) {
            return false;
        }
        $clusterSizeFile = $this->fileService->getResultFilePath($job, 'color_util_internal_mapping', true);
        $clusterSizes = $this->parseClusterSizeFile($clusterSizeFile);
        return isset($clusterSizes[$clusterId]);
    }

    /**
     * Parse the UniProt-UniRef cluster sizes text file and return an array mapping cluster ID
     * to sizes.
     * @param {ESTClusterAnalysisJob} $job - job
     * @returns {array} ['cluster_id' => ['uniprot' => 0, 'uniref90' => 0, 'uniref50' => 0], ...]
     */
    public function parseUnirefClusterSizeFile(ESTClusterAnalysisJob $job): array
    {
        $sequenceNodeNumberMapping = $this->getSequenceNodeClusterMapping($job);

        $filePath = $this->fileService->getResultFilePath($job, 'color_util_cluster_sizes', true);

        $data = file($filePath, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);

        $sizes = [];

        $header = null;
        foreach ($data as $lineNumber => $line) {
            if (!$header) {
                $header = $line;
                continue;
            }

            $parts = explode("\t", $line);
            $data = [ 'uniprot' => null, 'uniref90' => null, 'uniref50' => null ];
            if (count($parts) > 1)
                $data['uniprot'] = $parts[1];
            if (count($parts) > 2)
                $data['uniref90'] = $parts[2];
            if (count($parts) > 3)
                $data['uniref50'] = $parts[3];

            //TODO: remove this manual construction of the cluster ID
            $clusterId = is_numeric($parts[0]) ? sprintf("Cluster_%d", $parts[0]) : $parts[0];

            $data['node_cluster_id'] = $sequenceNodeNumberMapping[$clusterId] ?? $clusterId;

            $sizes[$clusterId] = $data;
        }

        ksort($sizes);

        return $sizes;
    }

    private function getSequenceNodeClusterMapping(ESTClusterAnalysisJob $job): array
    {
        $filePath = $this->fileService->getResultFilePath($job, 'color_util_internal_mapping', true);

        $data = file($filePath, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);

        $mapping = [];

        $header = null;
        foreach ($data as $lineNumber => $line) {
            if (!$header) {
                $header = $line;
                continue;
            }

            $parts = explode("\t", $line);

            //TODO: remove this manual construction of the cluster ID
            $clusterId = is_numeric($parts[0]) ? sprintf("Cluster_%d", $parts[0]) : $parts[0];

            if (count($parts) > 2) {
                //TODO: remove this manual construction of the cluster ID
                $nodeClusterId = is_numeric($parts[2]) ? sprintf("Cluster_%d", $parts[2]) : $parts[2];
                $mapping[$clusterId] = $nodeClusterId;
            } else {
                $mapping[$clusterId] = $clusterId;
            }
        }

        return $mapping;
    }

    /**
     * Get a list of clusters for the specified Cluster Analysis result file type.
     * @param {ESTClusterAnalysisJob} $job - job
     * @param {ClusterAnalysisFileType} $fileType - the file type
     * @param {?ClusterAnalysisSequenceType} $seqType - null, or one of the enum
     * @returns {array} array of cluster IDs mapped to file path, for clusters that have valid files for the specified file type
     */
    public function getClusterListing(ESTClusterAnalysisJob $job, ClusterAnalysisFileType $fileType, ?ClusterAnalysisSequenceType $seqType = null): array
    {
        $clusterSizeFile = $this->fileService->getResultFilePath($job, 'color_util_internal_mapping', true);
        $clusterSizes = $this->parseClusterSizeFile($clusterSizeFile);

        $jobDir = $this->fileService->getResultsDirectory($job);
        $subDirectory = $this->getFileTypeDirectoryName($fileType, $seqType);
        $resultsDir = $this->constructResultsDir($jobDir, $subDirectory);

        $fileExtension = $this->getFileTypeExtension($fileType);

        $this->logger->debug('getClusterListing', [
            'clusterSizeFile' => $clusterSizeFile,
            'clusterSizes' => $clusterSizes,
            'jobDir' => $jobDir,
            'subDirectory' => $subDirectory,
            'resultsDir' => $resultsDir,
            'fileExtension' => $fileExtension,
        ]);

        $clusterIds = [];
        foreach ($clusterSizes as $clusterId => $size) {
            $filePath = "$resultsDir/$clusterId.$fileExtension";
            if (file_exists($filePath)) {
                $clusterIds[$clusterId] = ['path' => $filePath, 'size' => $size];
            } else {
                $this->logger->warning('Expected cluster analysis result file missing.', [
                    'cluster_id' => $clusterId,
                    'file_type' => $fileType->value,
                    'file_path' => $filePath,
                    'job_id' => $job->getId(),
                ]);
            }
        }

        //uksort($clusterIds, 'strnatcasecmp');
        uksort($clusterIds, function ($a, $b) use ($clusterSizes) {
            $comp = $clusterSizes[$b] <=> $clusterSizes[$a];
            if ($comp === 0) {
                return strnatcasecmp($a, $b);
            } else {
                return $comp;
            }
        });

        return $clusterIds;
    }

    /**
     * Return a string containing the JSON data necessary for Skylign.
     * @param {ESTClusterAnalysisJob} $job - source of data
     * @param {string} $clusterId - cluster identifier
     * @param {bool} $isDomain - true if the SSN is based on domains
     * @returns {string} contents of JSON file for Skylign
     */
    public function getSkylignData(ESTClusterAnalysisJob $job, string $clusterId, bool $isDomain): string
    {
        $seqType = $isDomain ? ClusterAnalysisSequenceType::UniProtDomain : ClusterAnalysisSequenceType::UniProt;

        if (!$this->validateCluster($job, $clusterId)) {
            throw new \LogicException("Invalid cluster ID '{$clusterId}'.");
        }

        $jobDir = $this->fileService->getResultsDirectory($job);

        $subDirectory = $this->getFileTypeDirectoryName(ClusterAnalysisFileType::Hmm, $seqType);
        $resultsDir = $this->constructResultsDir($jobDir, $subDirectory);
        $jsonFilePath = "$resultsDir/$clusterId.json";

        $data = file_get_contents($jsonFilePath);

        return $data;
    }

    /**
     * Get the file path for the specific cluster and file type.
     *value @param {ESTClusterAnalysisJob} $job - source of data
     * @param {string} $clusterId - cluster identifier (ensure this value is valid by calling validateCluster() beforehand)
     * @param {ClusterAnalysisFileType} $fileType - the file type
     * @param {bool} $useDownloadResponse - set to true if the request is for downloading (allows high resolution downloads but small resolution display inline)
     * @param {?ClusterAnalysisSequenceType} $seqType - null, or one of uniprot, uniref90, uniref50, with optional _domain suffix
     * @returns {bool} true if the cluster exists, false otherwise
     */
    public function getResultFilePath(ESTClusterAnalysisJob $job, string $clusterId, ClusterAnalysisFileType $fileType, bool $useDownloadResponse, ?ClusterAnalysisSequenceType $seqType = null): string
    {
        if (!$this->validateCluster($job, $clusterId)) {
            throw new \LogicException("Invalid cluster ID '{$clusterId}' for result file path.");
        }

        $jobDir = $this->fileService->getResultsDirectory($job);

        $subDirectory = $this->getFileTypeDirectoryName($fileType, $seqType);
        $resultsDir = $this->constructResultsDir($jobDir, $subDirectory);
        $fileTypeExt = $this->getFileTypeExtension($fileType);

        $baseDir = "$resultsDir/$clusterId";

        $downloadExt = ($fileTypeExt === 'png' && !$useDownloadResponse) ? '_sm' : '';
        $downloadPath = "$baseDir$downloadExt.$fileTypeExt";
        $filePath = file_exists($downloadPath) ? $downloadPath : "$baseDir.$fileTypeExt";

        return $filePath;
    }

    /**
     * Return extra suffix information to be included on a downloaded file (e.g. UniRef, domain)
     * @param {?ClusterAnalysisSequenceType} $seqType - null, or one of uniprot, uniref90, uniref50, with optional _domain suffix
     * @return {?string}
     */
    public function getFilenameExtra(?ClusterAnalysisSequenceType $seqType): ?string
    {
        if (!$seqType) {
            return null;
        }
        return $this->getSequenceTypeValue($seqType);
    }

    /**
     * Get the path to the consensus residue result file for the given residue.
     * @param {ESTClusterAnalysisJob} $job - source of data
     * @param {string} $residue - an amino acid residue (one of [A-Z])
     * @param {?ClusterAnalysisSequenceType} $seqType - null, or one of uniprot, uniref90, uniref50, with optional _domain suffix
     * @returns {string} path to result file
     */
    public function getConsensusResidueFilePath(ESTClusterAnalysisJob $job, string $residue, ?ClusterAnalysisSequenceType $seqType): string
    {
        if (!preg_match('/^[A-Z]$/', $residue)) {
            throw new \LogicException("Invalid residue '{$residue}' for consensus residue file path.");
        }

        $jobDir = $this->fileService->getResultsDirectory($job);

        $subDirectory = $this->getFileTypeDirectoryName(ClusterAnalysisFileType::ConsensusResidue, $seqType);
        $resultsDir = $this->constructResultsDir($jobDir, $subDirectory);
        $fileTypeExt = $this->getFileTypeExtension(ClusterAnalysisFileType::ConsensusResidue);

        $filePath = "$resultsDir/ConsensusResidue_{$residue}_Position_Summary_Full.txt";

        return $filePath;
    }

    /**
     * Gets the corresponding ClusterAnalysisSequenceType for the given query request.
     * @param {string} $seqType - null, or values from ClusterAnalysisSequenceType
     * @return {ClusterAnalysisSequenceType} one of the enums; defaults to UniProt
     */
    public function getDomainSequenceType(?string $seqType): ClusterAnalysisSequenceType
    {
        return match($seqType) {
            'uniprot' => ClusterAnalysisSequenceType::UniProt,
            'uniprot_domain' => ClusterAnalysisSequenceType::UniProtDomain,
            'uniref90' => ClusterAnalysisSequenceType::UniRef90,
            'uniref90_domain' => ClusterAnalysisSequenceType::UniRef90Domain,
            'uniref50' => ClusterAnalysisSequenceType::UniRef50,
            'uniref50_domain' => ClusterAnalysisSequenceType::UniRef50Domain,
            default => ClusterAnalysisSequenceType::UniProt
        };
    }

    /**
     * Returns the directory name corresponding to the sequence type.
     * @param {?ClusterAnalysisSequenceType} $seqType
     * @return {?string}
     */
    public function getSequenceTypeValue(?ClusterAnalysisSequenceType $seqType): ?string
    {
        return match($seqType) {
            ClusterAnalysisSequenceType::UniProt => 'uniprot',
            ClusterAnalysisSequenceType::UniProtDomain => 'uniprot_domain',
            ClusterAnalysisSequenceType::UniRef90 => 'uniref90',
            ClusterAnalysisSequenceType::UniRef90Domain => 'uniref90_domain',
            ClusterAnalysisSequenceType::UniRef50 => 'uniref50',
            ClusterAnalysisSequenceType::UniRef50Domain => 'uniref50_domain',
            default => null
        };
    }

    /**
     * Indicates if the given sequence type is domain.
     * @param {?ClusterAnalysisSequenceType} $seqType
     * @return {bool} true if UniProt, UniRef90, or UniRef50 domain
     */
    public function isDomainSequenceType(?ClusterAnalysisSequenceType $seqType): bool
    {
        return match($seqType) {
            ClusterAnalysisSequenceType::UniProtDomain => true,
            ClusterAnalysisSequenceType::UniRef90Domain => true,
            ClusterAnalysisSequenceType::UniRef50Domain => true,
            default => false
        };
    }




    /**
     * Return the file extension for the specified file type.
     * @param {ClusterAnalysisFileType} $fileType - the file type
     * @returns {string} file extension
     */
    private function getFileTypeExtension(ClusterAnalysisFileType $fileType): string
    {
        return match($fileType) {
            ClusterAnalysisFileType::ConsensusResidue => 'tab',
            ClusterAnalysisFileType::Hmm => 'hmm',
            ClusterAnalysisFileType::LengthHistogram => 'png',
            ClusterAnalysisFileType::Msa => 'afa',
            ClusterAnalysisFileType::Weblogo => 'png',
            default => ''
        };
    }

    /**
     * Return the sub-directory name for the specified file type.
     * @param {ClusterAnalysisFileType} $fileType - the file type
     * @returns {string} directory name (not path)
     */
    private function getFileTypeDirectoryName(ClusterAnalysisFileType $fileType, ?ClusterAnalysisSequenceType $seqType = null): string
    {
        $subDir = match($fileType) {
            ClusterAnalysisFileType::ConsensusResidue => 'cons_res',
            ClusterAnalysisFileType::Hmm => 'hmms',
            ClusterAnalysisFileType::LengthHistogram => 'histo',
            ClusterAnalysisFileType::Msa => 'msa',
            ClusterAnalysisFileType::Weblogo => 'weblogos',
        };

        if ($seqType) {
            if ($fileType === ClusterAnalysisFileType::LengthHistogram) {
                $seqDir = $this->getSequenceTypeValue($seqType);
            } else {
                $seqDir = in_array($seqType, [ClusterAnalysisSequenceType::UniProtDomain, ClusterAnalysisSequenceType::UniRef90Domain, ClusterAnalysisSequenceType::UniRef50Domain])
                    ? "domain"
                    : "full";
            }
            return "$subDir/$seqDir";
        } else {
            return $subDir;
        }
    }

    /**
     * Create a path to the specific cluster analysis results directory.
     * @param {string} $jobDir - full path to the job directory
     * @param {string} $subDir - name of the sub-directory
     * @returns {string} path to results directory
     */
    private function constructResultsDir(string $jobDir, string $subDir): string
    {
        $dir = "$jobDir/data/$subDir";
        return $dir;
    }

    /**
     * Parse the cluster sizes text file and return an array mapping cluster ID to size.
     * @param {string} $filePath - path to cluster size file
     * @param {bool} $sortBySsnClusterSize - true: sort results by SSN cluster size (nodes); false: sort results by sequence cluster size
     * @returns {array} ['cluster_id' => NUM_SEQ, ...]
     */
    private function parseClusterSizeFile(string $filePath, bool $sortBySsnClusterSize = true): array
    {
        $data = file($filePath, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);

        $sizes = [];

        $header = null;
        foreach ($data as $lineNumber => $line) {
            if (!$header) {
                $header = $line;
                continue;
            }

            $parts = explode("\t", $line);
            if (count($parts) > 1) {
                if ($sortBySsnClusterSize && count($parts) > 3) {
                    $id = $parts[2];
                    $val = $parts[3];
                } else {
                    $id = $parts[0];
                    $val = $parts[1];
                }

                // Handle legacy cases
                if (is_numeric($id)) {
                    $id = "Cluster_{$id}";
                }

                $sizes[$id] = $val;
            }
        }

        uksort($sizes, function ($a, $b) use ($sizes) { return $sizes[$b] <=> $sizes[$a]; });

        return $sizes;
    }
}
