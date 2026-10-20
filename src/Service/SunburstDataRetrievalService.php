<?php

namespace App\Service;

use App\Entity\Job;
use App\Service\ResultFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag; // For Content-Disposition
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

class SunburstDataRetrievalService
{
    public function __construct(
        private readonly string $blastBinDir,
        private readonly LoggerInterface $logger,
        private readonly ResultFileService $resultService,
    )
    {
    }

    /**
     * Get JSON data for the sunburst taxonomy viewer.
     * @param {Job} $job - job object
     * @return {array} - array deserialized from JSON
     */
    public function getData(Job $job): array
    {
        $sunburstData = ['data' => []];

        try {
            $dataFile = $this->resultService->getResultFilePath($job, 'sunburst_json');

            if ($dataFile !== null) {
                $jsonString = file_get_contents($dataFile);
                $sunburstData['data'] = json_decode($jsonString);
            }
        } catch (\Exception $e) {
        }

        return $sunburstData;
    }

    /**
     * Get the suffix at the end of the file name, before the extension.
     */
    public function getDownloadFilenameSuffix(?string $nodeNameQuery, ?string $idTypeQuery): string
    {
        $idType = $idTypeQuery === 'uniref90'
            ? 'UniRef90'
            : ($idTypeQuery === 'uniref50'
                ? 'UniRef50'
                : 'UniProt');
        $nodeName = ($nodeNameQuery ?? 'sunburst') . '_' . $idType;
        return $nodeName;
    }

    /**
     * Retrieves sequences from the specified BLAST database using the list of IDs.
     * @param {Job} $job - job object
     * @param {array} $ids - list of sequence accessions/IDs
     * @param {string} $nodeName - name of the sunburst taxonomy node, for download filename
     * @return {StreamedResponse} - response that doesn't require storing entire data in memory
     */
    public function getFastaByIds(Job $job, array $ids, string $nodeName): StreamedResponse
    {
        $jobDir = $this->resultService->getResultsDirectory($job);
        $fastaDbPath = "$jobDir/database";

        $filesystem = new Filesystem();

        $inputIdFile = tempnam(sys_get_temp_dir(), 'blast_ids_');
        file_put_contents($inputIdFile, implode("\n", $ids));

        $outputSeqFile = tempnam(sys_get_temp_dir(), 'blast_output_');

        $process = new Process([
            $this->blastBinDir . '/fastacmd',
            '-d', $fastaDbPath,
            '-i', $inputIdFile,
            '-o', $outputSeqFile,
        ]);

        $response = new StreamedResponse(function () use ($fastaDbPath, $filesystem, $inputIdFile, $nodeName, $outputSeqFile, $process) {
            try {
                $process->mustRun();

                if (file_exists($outputSeqFile)) {
                    $this->sendFastaFile($outputSeqFile);
                }
            } catch (ProcessFailedException $exception) {
                // In a StreamedResponse, we can't change the HTTP status code once the callback
                // starts, so we write the error as a FASTA comment
                $this->logger->warning('BLAST fastacmd failed, likely due to some sequences being missing from a UniProt-UniRef mismatch', [
                    'command' => $process->getCommandLine(),
                    'error'   => $exception->getMessage(),
                    'db'      => $fastaDbPath,
                    'exitCode' => $process->getExitCode()
                ]);

                // If the sequence database version is mismatched with the ID then 'fastacmd' will still run but
                // will return error code because some are missing from the file.  We ignore that.
                if (file_exists($outputSeqFile)) {
                    $this->sendFastaFile($outputSeqFile);
                }
            } catch (\Exception $e) {
                $this->logger->critical('Unexpected error in SunburstDataRetrievalService', [
                    'message' => $e->getMessage()
                ]);
                echo "\n>Error: Failed to retrieve sequences from BLAST database (2).\n";
            } finally {
                $filesystem->remove([$inputIdFile, $outputSeqFile]);
            }
        });

        $filename = $this->resultService->getDownloadFilenameFromJob($job, 'sunburst_fasta', 'fasta', $nodeName);

        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    private function sendFastaFile(string $outputSeqFile) {
        $input = fopen($outputSeqFile, 'rb');

        while (($line = fgets($input)) !== false) {
            if (str_starts_with($line, '>lcl|')) {
                $line = preg_replace('/>lcl\|([^ ]+) .*/', '>$1', $line);
            }
            echo $line;
            flush();
        }

        fclose($input);
    }
}
