<?php

namespace App\Tests\Service;

use App\Service\ResultFileService;
use App\Tests\BaseServiceTestCase;
use App\Tests\Factory\ESTGenerateFamiliesJobFactory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResultFileServiceTest extends BaseServiceTestCase
{
    private ResultFileService $service;
    private string $resultsDir;
    private string $subdirectoryName;

    protected function setUp(): void
    {
        parent::setUp();
        // Get the configured results directory from parameters
        $this->resultsDir = $this->container->getParameter('app.results_directory');
        $this->subdirectoryName = $this->container->getParameter('app.results_subdirectory_name');
        $this->service = $this->container->get(ResultFileService::class);
    }

    protected function tearDown(): void
    {
        // Cleanup: Remove the test results directory after tests run
        $fs = new Filesystem();
        if ($fs->exists($this->resultsDir)) {
            $fs->remove($this->resultsDir);
        }
        parent::tearDown();
    }

    public function testGetResultFilePathReturnsValidPath(): void
    {
        $job = $this->createDefaultJob();

        $jobId = $job->getId();

        // Manually create the file in the results directory where we expect the file to be located
        $expectedDir = $this->resultsDir . '/' . $jobId . '/' . $this->subdirectoryName; 

        $fileType = 'alignment_length';
        $filename = 'alignment_length.png';

        $fs = new Filesystem();
        $fullPath = $expectedDir . '/' . $filename;
        $fs->mkdir($expectedDir);
        $fs->touch($expectedDir . '/' . $filename);

        // Get the path that the service expects the file to be at
        $path = $this->service->getResultFilePath($job, $fileType, false);

        $this->assertFileExists($path);
        $this->assertStringEndsWith($filename, $path);
    }

    public function testGetResultFilePathReturnsValidJsonPath(): void
    {
        $job = $this->createDefaultJob();

        $jobId = $job->getId();
        $expectedDir = $this->resultsDir . '/' . $jobId . '/' . $this->subdirectoryName;
        $fileType = 'alignment_length_json';
        $filename = 'alignment_length.json';

        $fs = new Filesystem();
        $fs->mkdir($expectedDir);
        $fullPath = $expectedDir . '/' . $filename;
        $fs->dumpFile($fullPath, json_encode(['data' => [], 'layout' => []]));

        $path = $this->service->getResultFilePath($job, $fileType, false);

        $this->assertFileExists($path);
        $this->assertStringEndsWith($filename, $path);
    }

    public function testGetResultFileInvalidPathFailure(): void
    {
        $job = $this->createDefaultJob();

        $jobId = $job->getId();

        $fileType = 'alignment_length';

        // Get the file path and delete it so the test runs correctly and the getResultFilePath call
        // below throws an exception
        $path = $this->service->getResultFilePath($job, $fileType, false);

        if (file_exists($path)) {
            unlink($path);
        }

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageMatches('#Requested file .*not found#');

        // This call should fail with an exception
        $path = $this->service->getResultFilePath($job, $fileType);
    }

    public function testGetCleanFilenameSanitizesInput(): void
    {
        // "Bad / File \ Name" -> "Bad_File_Name"
        $dirty = "Bad / File \ Name @!";
        $clean = $this->service->getCleanFilename($dirty);

        $this->assertEquals('Bad__File__Name_', $clean);
    }

    public function testGetDownloadFilenameFormatting(): void
    {
        $filename = $this->service->getDownloadFilenameFromName('My Job', 'est_dataset_summary');
        $this->assertEquals('My_Job_dataset_summary.txt', $filename);
    }

    public function testGetDownloadFilenameDefaultExtFormatting(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage("File type 'invalid_item' is not defined.");

        $filename = $this->service->getDownloadFilenameFromName('My Job', 'invalid_item', 'txt');

        $this->assertEquals('My_Job_invalid_item.txt', $filename);
    }

    public function testGetJobDownloadFilenameFormatting(): void
    {
        $job = $this->createDefaultJob(BaseServiceTestCase::JOB_NAME);
        $filename = $this->service->getDownloadFilenameFromJob($job, 'est_dataset_summary');
        $cleanName = str_replace(' ', '_', BaseServiceTestCase::JOB_NAME);
        $expectedFilename = sprintf('1_%s_dataset_summary.txt', $cleanName);
        $this->assertEquals($expectedFilename, $filename);
    }
}
