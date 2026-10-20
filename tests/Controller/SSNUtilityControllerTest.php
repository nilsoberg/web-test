<?php

namespace App\Tests\Controller;

use App\Entity\ESTClusterAnalysisJob;
use App\Enum\ClusterAnalysisFileType;
use App\Enum\ClusterAnalysisSequenceType;
use App\EST\Controller\SSNUtilityController;
use App\Service\ClusterAnalysisResultFileService;
use App\Service\JobRetrieverService;
use App\Service\ResultFileDownloadService;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

class SSNUtilityControllerTest extends TestCase
{
    public function testClusterAnalysisDownloadReturns400ForInvalidClusterId(): void
    {
        $job = new ESTClusterAnalysisJob();
        $jobRetrieverService = $this->createMock(JobRetrieverService::class);
        $downloadService = $this->createMock(ResultFileDownloadService::class);
        $analysisService = $this->createMock(ClusterAnalysisResultFileService::class);
        $analysisService->method('getDomainSequenceType')->willReturn(ClusterAnalysisSequenceType::UniProt);
        $logger = $this->createMock(LoggerInterface::class);

        $jobRetrieverService->expects($this->once())
            ->method('findJob')
            ->with(123, $this->callback(fn (string $uuid): bool => Uuid::isValid($uuid)))
            ->willReturn($job);

        $analysisService->expects($this->once())
            ->method('validateCluster')
            ->with($job, 'bad_cluster')
            ->willReturn(false);

        $analysisService->expects($this->never())->method('validateFileType');
        $analysisService->expects($this->never())->method('getResultFilePath');

        $controller = new SSNUtilityController($jobRetrieverService, $logger, $downloadService);

        try {
            $controller->cluster_analysis_download(
                123,
                Uuid::v6(),
                ClusterAnalysisFileType::Weblogo->value,
                'bad_cluster',
                new Request(),
                $analysisService
            );
            $this->fail('Expected BadRequestHttpException was not thrown.');
        } catch (BadRequestHttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame('Invalid cluster ID.', $exception->getMessage());
        }
    }

    public function testClusterAnalysisDownloadReturns400ForInvalidFileType(): void
    {
        $job = new ESTClusterAnalysisJob();
        $jobRetrieverService = $this->createMock(JobRetrieverService::class);
        $downloadService = $this->createMock(ResultFileDownloadService::class);
        $analysisService = $this->createMock(ClusterAnalysisResultFileService::class);
        $analysisService->method('getDomainSequenceType')->willReturn(ClusterAnalysisSequenceType::UniProt);
        $logger = $this->createMock(LoggerInterface::class);

        $jobRetrieverService->expects($this->once())
            ->method('findJob')
            ->with(456, $this->callback(fn (string $uuid): bool => Uuid::isValid($uuid)))
            ->willReturn($job);

        $analysisService->expects($this->once())
            ->method('validateCluster')
            ->with($job, 'cluster_1')
            ->willReturn(true);

        $analysisService->expects($this->once())
            ->method('validateFileType')
            ->with('not_a_real_file_type')
            ->willReturn(null);

        $analysisService->expects($this->never())->method('getResultFilePath');

        $controller = new SSNUtilityController($jobRetrieverService, $logger, $downloadService);

        try {
            $controller->cluster_analysis_download(
                456,
                Uuid::v6(),
                'not_a_real_file_type',
                'cluster_1',
                new Request(),
                $analysisService
            );
            $this->fail('Expected BadRequestHttpException was not thrown.');
        } catch (BadRequestHttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame('Invalid file type.', $exception->getMessage());
        }
    }

    public function testClusterAnalysisDownloadReturns400ForMissingResultFileWithoutPathLeak(): void
    {
        $job = new ESTClusterAnalysisJob();
        $jobRetrieverService = $this->createMock(JobRetrieverService::class);
        $downloadService = $this->createMock(ResultFileDownloadService::class);
        $analysisService = $this->createMock(ClusterAnalysisResultFileService::class);
        $analysisService->method('getDomainSequenceType')->willReturn(ClusterAnalysisSequenceType::UniProt);
        $logger = $this->createMock(LoggerInterface::class);
        $missingPath = sys_get_temp_dir() . '/ca_result_missing_' . uniqid('', true) . '.png';

        $jobRetrieverService->expects($this->once())
            ->method('findJob')
            ->with(789, $this->callback(fn (string $uuid): bool => Uuid::isValid($uuid)))
            ->willReturn($job);

        $analysisService->expects($this->once())
            ->method('validateCluster')
            ->with($job, 'cluster_2')
            ->willReturn(true);

        $analysisService->expects($this->once())
            ->method('validateFileType')
            ->with(ClusterAnalysisFileType::Weblogo->value)
            ->willReturn(ClusterAnalysisFileType::Weblogo);

        $analysisService->expects($this->once())
            ->method('getResultFilePath')
            ->with($job, 'cluster_2', ClusterAnalysisFileType::Weblogo, false)
            ->willReturn($missingPath);

        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Result file missing.',
                $this->callback(static function (array $context) use ($missingPath): bool {
                    return $context['file_path'] === $missingPath
                        && array_key_exists('job_id', $context)
                        && $context['file_type'] === ClusterAnalysisFileType::Weblogo->value
                        && $context['cluster_id'] === 'cluster_2';
                })
            );

        $controller = new SSNUtilityController($jobRetrieverService, $logger, $downloadService);

        try {
            $controller->cluster_analysis_download(
                789,
                Uuid::v6(),
                ClusterAnalysisFileType::Weblogo->value,
                'cluster_2',
                new Request(),
                $analysisService
            );
            $this->fail('Expected BadRequestHttpException was not thrown.');
        } catch (BadRequestHttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame('Requested result file does not exist.', $exception->getMessage());
            $this->assertStringNotContainsString($missingPath, $exception->getMessage());
        }
    }

    public function testConsensusResidueDownloadReturns400ForMissingFileWithoutPathLeak(): void
    {
        $job = new ESTClusterAnalysisJob();
        $jobRetrieverService = $this->createMock(JobRetrieverService::class);
        $downloadService = $this->createMock(ResultFileDownloadService::class);
        $analysisService = $this->createMock(ClusterAnalysisResultFileService::class);
        $analysisService->method('getDomainSequenceType')->willReturn(ClusterAnalysisSequenceType::UniProt);
        $logger = $this->createMock(LoggerInterface::class);
        $missingPath = sys_get_temp_dir() . '/ca_consensus_missing_' . uniqid('', true) . '.tab';

        $jobRetrieverService->expects($this->once())
            ->method('findJob')
            ->with(1001, $this->callback(fn (string $uuid): bool => Uuid::isValid($uuid)))
            ->willReturn($job);

        $analysisService->expects($this->once())
            ->method('getConsensusResidueFilePath')
            ->with($job, 'C')
            ->willReturn($missingPath);

        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Consensus residue result file missing.',
                $this->callback(static function (array $context) use ($missingPath): bool {
                    return $context['file_path'] === $missingPath
                        && array_key_exists('job_id', $context)
                        && $context['residue'] === 'C';
                })
            );

        $controller = new SSNUtilityController($jobRetrieverService, $logger, $downloadService);

        try {
            $controller->consensus_residue_download(
                1001,
                Uuid::v6(),
                'C',
                new Request(),
                $analysisService
            );
            $this->fail('Expected BadRequestHttpException was not thrown.');
        } catch (BadRequestHttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame('Requested consensus residue result file does not exist.', $exception->getMessage());
            $this->assertStringNotContainsString($missingPath, $exception->getMessage());
        }
    }
}
