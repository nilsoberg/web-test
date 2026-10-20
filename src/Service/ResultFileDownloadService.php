<?php

namespace App\Service;

use App\Entity\Job;
use App\Config\Constants;
use App\Service\ResultFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag; // For Content-Disposition
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ResultFileDownloadService
{
    public function __construct(
        private LoggerInterface $logger,
        private ResultFileService $fileService
    )
	{
    }

    /**
     * Send a file (e.g. image, zip) to the browser for downloading.
     * @param {Job} $job - job that the file belongs to
     * @param {string} $fileTypeAlias - an alias from the config/custom_file_types.yaml file
     * @return {BinaryFileResponse} download response (attachment)
     */
    public function createDownloadResponse(Job $job, string $fileTypeAlias): BinaryFileResponse
    {
        return $this->createResultFileResponseFromJob($job, $fileTypeAlias, forceDownload: true);
    }

    /**
     * Send a file (e.g. image) to the browser for displaying, not downloading.
     * @param {Job} $job - job that the file belongs to
     * @param {string} $fileTypeAlias - an alias from the config/custom_file_types.yaml file
     * @return {BinaryFileResponse} display response (inline)
     */
    public function createDisplayResponse(Job $job, string $fileTypeAlias): BinaryFileResponse
    {
        return $this->createResultFileResponseFromJob($job, $fileTypeAlias, forceDownload: false);
    }

    /**
     * Send a file to the browser for downloading, based on a local file path not a Job.
     * @param {string} $filePath - path to server-local file
     * @param {string} $jobName - name of the job that the file originated from
     * @param {string} $fileTypeAlias - an alias from the config/custom_file_types.yaml file
     * @param {string} $extraName - additional information to include in file name
     * @return {BinaryFileResponse} download response sent to the browser (for zip, etc)
     */
    public function createDownloadResponseFromPathAndName(string $filePath, string $jobName, string $fileTypeAlias, ?string $extraName = null): BinaryFileResponse
    {
        $downloadFilename = $this->fileService->getDownloadFilenameFromName($jobName, $fileTypeAlias, null, $extraName);
        return $this->createBinaryFileResponse($filePath, $downloadFilename, forceDownload: true);
    }

    /**
     * Send a file to the browser for displaying (e.g. image), based on a local file path not a Job.
     * @param {string} $filePath - path to server-local file
     * @param {string} $jobName - name of the job that the file originated from
     * @param {string} $fileTypeAlias - an alias from the config/custom_file_types.yaml file
     * @param {string} $extraName - additional information to include in file name
     * @return {BinaryFileResponse} display response (inline)
     */
    public function createDisplayResponseFromPathAndName(string $filePath, string $jobName, string $fileTypeAlias, ?string $extraName = null): BinaryFileResponse
    {
        $downloadFilename = $this->fileService->getDownloadFilenameFromName($jobName, $fileTypeAlias, null, $extraName);
        return $this->createBinaryFileResponse($filePath, $downloadFilename, forceDownload: false);
    }

    /**
     * Create a response that sends a tabular text file from a job.  Used to generate summary tables
     * from job metadata.
     * @param {Job} $job - job (for file naming)
     * @param {array} $summaryData - 2-D array of data; each row has 'label' and 'value' key-values
     * @param {string} $fileTypeAlias - an alias from the config/custom_file_types.yaml file
     * @return {StreamedResponse} streamed response passed to the browser
     */
    public function createDownloadTextResponse(Job $job, array $summaryData, string $fileTypeAlias): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($summaryData) {
            $output = fopen('php://output', 'w');

            foreach ($summaryData as $row) {
                fwrite($output, $row['label'] . "\t" . strip_tags($row['value']) . "\n");
            }
            fclose($output);
        });

        $filename = $this->fileService->getDownloadFilenameFromJob($job, $fileTypeAlias, defaultExtension: Constants::DEFAULT_TEXT_DOWNLOAD_EXTENSION);

        $response->headers->set('Content-Type', 'text/tab-separated-values');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Create a response that streams the input data source to the browser rather than aggregating
     * into a complete file before sending to the browser.  Used for sending large files.
     * @param {\IteratorAggregate} $generator - the source of the data
     * @param {string} $filename - name of the file that the browser will see
     * @param {string} $fileTypeAlias - an alias from the config/custom_file_types.yaml file
     * @return {StreamedResponse} streamed response passed to the browser
     */
    public function createStreamedDownloadResponse(\IteratorAggregate $generator, string $filename, string $fileTypeAlias): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($generator) {
            $output = fopen('php://output', 'w');
            foreach ($generator as $chunk) {
                fwrite($output, $chunk);
            }
            fclose($output);
        });

        return $this->prepareTextResponse($response, $filename, $fileTypeAlias, 'text/plain');
    }

    /**
     * Create a streamed response for text data.
     */
    private function prepareTextResponse(StreamedResponse $response, string $filename, string $fileTypeAlias, string $contentType): StreamedResponse
    {
        $filename = $this->fileService->getDownloadFilenameFromName($filename, $fileTypeAlias, defaultExtension: Constants::DEFAULT_TEXT_DOWNLOAD_EXTENSION);

        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * Creates a BinaryFileResponse for a given job and file type alias.
     * The file type alias is mapped to an actual server filename and a download filename.
     */
    private function createResultFileResponseFromJob(Job $job, string $fileTypeAlias, bool $forceDownload): BinaryFileResponse
    {
        $filePath = $this->fileService->getResultFilePath($job, $fileTypeAlias);

        $downloadFilename = $this->fileService->getDownloadFilenameFromJob($job, $fileTypeAlias);

        return $this->createBinaryFileResponse($filePath, $downloadFilename, $forceDownload);
    }

    /**
     * Return BinaryFileResponse with custom download filename
     */
    private function createBinaryFileResponse(string $filePath, string $downloadFilename, bool $forceDownload): BinaryFileResponse
    {
        $response = new BinaryFileResponse($filePath);

        // Use DISPOSITION_INLINE to try to display in browser (e.g. for inline images), ATTACHMENT to force download
        if ($forceDownload) {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadFilename);
        } else {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $downloadFilename);
        }

        return $response;
    }
}
