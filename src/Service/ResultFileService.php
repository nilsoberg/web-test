<?php

namespace App\Service;

use App\Entity\ESTSSNFinalizationJob;
use App\Entity\Job;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ResultFileService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $resultsDirectory,
        private readonly string $resultsSubdirectoryName,
        private readonly array $fileTypeMappings,
    )
	{
    }

    public function getFileTypeMapping(string $fileTypeAlias): array
    {
        // Look up fileTypeAlias in mappings
        if (!isset($this->fileTypeMappings[$fileTypeAlias])) {
            $this->logger->error("Undefined file type alias", [
                'file_type_alias' => $fileTypeAlias,
            ]);
            throw new NotFoundHttpException("File type '{$fileTypeAlias}' is not defined.");
        }
        $mapping = $this->fileTypeMappings[$fileTypeAlias];
        return $mapping;
    }

    /**
     * Return the directory containing results for the specified job.
     * @param {Job} $job
     * @returns {string} path to results directory
     */
    public function getResultsDirectory(Job $job): string
    {
        if ($this->resultsSubdirectoryName) {
            $dirPath = sprintf('%s/%s/%s',
                $this->resultsDirectory,
                $job->getId(),
                $this->resultsSubdirectoryName,
            );
        } else {
            $dirPath = sprintf('%s/%s',
                $this->resultsDirectory,
                $job->getId()
            );
        }
        return $dirPath;
    }

    public function getResultFilePath(Job $job, string $fileTypeAlias, bool $validateExistence = true): string
    {
        $mapping = $this->getFileTypeMapping($fileTypeAlias);
        $actualServerFilenameOnDisk = $mapping['server_filename'] ?? null;
        $downloadSuffix = $mapping['download_suffix'] ?? $fileTypeAlias; // Fallback for suffix
        $downloadExtension = $mapping['extension'] ?? pathinfo($actualServerFilenameOnDisk, PATHINFO_EXTENSION);

        if (empty($actualServerFilenameOnDisk)) {
            $this->logger->error("Configuration error: 'server_filename' missing for alias", [
                'job_id' => $job->getId(),
                'file_type_alias' => $fileTypeAlias,
            ]);
            throw new \LogicException("Server file configuration error for type '{$fileTypeAlias}'.");
        }

        // Sanitize actualServerFilenameOnDisk (even from config, as good practice)
        $cleanedActualServerFilename = basename($actualServerFilenameOnDisk);
        if ($cleanedActualServerFilename !== $actualServerFilenameOnDisk || empty($cleanedActualServerFilename)) {
            $this->logger->error("Configuration error: 'server_filename' contains path components or is invalid.", [
                'job_id' => $job->getId(),
                'path' => $actualServerFilenameOnDisk,
                'file_type_alias' => $fileTypeAlias,
            ]);
            throw new \LogicException("Invalid server filename configuration for type '{$fileTypeAlias}'.");
        }

        $jobId = $job->getId();
        $dirPath = $this->getResultsDirectory($job);
        // Construct the secure file path
        $filePath = sprintf(
            '%s/%s',
            $dirPath,
            $cleanedActualServerFilename
        );

        // Check file existence and ensure it's a file
        if ($validateExistence === true && (!file_exists($filePath) || !is_file($filePath))) {
            $this->logger->error("File not found at path", [
                'job_id' => $jobId,
                'path' => $filePath,
                'file_type_alias' => $fileTypeAlias,
            ]);
            throw new NotFoundHttpException("Requested file '{$filePath}' not found.");
        }

        return $filePath;
    }

    public function getCleanFilename(string $jobName): string
    {
        $cleanName = trim($jobName);
        $cleanName = preg_replace('/\s+/', '_', $cleanName);
        $cleanName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $cleanName);
        return $cleanName;
    }

    /**
     * Get the filename for the requested job result file type.  This prefixes the file name with
     * the job ID (and in the case of a SSN finalization job, the parent job ID as well).
     * @param {Job} $job - the job the file belongs to; the job name forms the stem of the file
     * @param {string} $fileTypeAlias - an alias from the config/custom_file_types.yaml file
     * @param {string} $defaultExtension - default download extension (if fileTypeAlias is not valid)
     * @param {string} $extraName - additional information to include in file name
     * @return {string} name of the file streamed to or downloaded from the browser
     */
    public function getDownloadFilenameFromJob(Job $job, string $fileTypeAlias, string $defaultExtension = '', string $extraName = ''): string
    {
        $filename = $this->getBaseDownloadFilename($job->getJobName() ?? '', $fileTypeAlias, $defaultExtension, $extraName);

        if ($job instanceof ESTSSNFinalizationJob) {
            $filename = sprintf('%s_%s_%s', $job->getParentJob()->getId(), $job->getId(), $filename);
        } else {
            $filename = sprintf('%s_%s', $job->getId(), $filename);
        }

        return $filename;
    }

    /**
     * Get the filename for the requested result file type.
     * @param {string} $jobName - the name of the job (or other name) to use as the stem of the file
     * @param {string} $fileTypeAlias - an alias from the config/custom_file_types.yaml file
     * @param {string} $defaultExtension - default download extension (if fileTypeAlias is not valid)
     * @param {string} $extraName - additional information to include in file name
     * @return {string} name of the file streamed to or downloaded from the browser
     */
    public function getDownloadFilenameFromName(string $jobName, string $fileTypeAlias, ?string $defaultExtension = null, ?string $extraName = null): string
    {
        $filename = $this->getBaseDownloadFilename($jobName, $fileTypeAlias, $defaultExtension, $extraName);
        return $filename;
    }

    private function getBaseDownloadFilename(string $jobName, string $fileTypeAlias, ?string $defaultExtension, ?string $extraName = null): string
    {
        if ($extraName) {
            $jobName = "{$jobName}_{$extraName}";
        }

        $mapping = $this->getFileTypeMapping($fileTypeAlias);
        $downloadSuffix = $mapping['download_suffix'] ?? $fileTypeAlias; // Fallback for suffix

        // Handle case when suffix is empty
        if ($downloadSuffix) {
            $downloadSuffix = "_{$downloadSuffix}";
        }

        $downloadExtension = $mapping['extension'] ?? $defaultExtension ?? '';
        $cleanName = $this->getCleanFilename($jobName);
        $filename = sprintf('%s%s.%s', $cleanName, $downloadSuffix, $downloadExtension);
        return $filename;
    }
}
