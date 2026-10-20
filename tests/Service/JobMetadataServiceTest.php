<?php

namespace App\Tests\Service;

use App\Config\Constants;
use App\Config\Settings;
use App\Entity\ESTGenerateBlastJob;
use App\Service\ConfigurationManager;
use App\Service\JobMetadataService;
use PHPUnit\Framework\TestCase;

class JobMetadataServiceTest extends TestCase
{
    public function testCollectMetadataReturnsStructuredSequencePreviewInsteadOfRawHtml(): void
    {
        $uploadsDirectory = sys_get_temp_dir() . '/job-metadata-' . uniqid('', true);
        mkdir($uploadsDirectory, 0777, true);

        $jobFilename = 'sequence.fasta';
        $sequence = "\"><script>alert(1)</script>\nMSTNPKPQRITF";
        file_put_contents($uploadsDirectory . '/' . $jobFilename, $sequence);

        $config = $this->createMock(ConfigurationManager::class);
        $config->expects($this->any())
            ->method('get')
            ->with(Settings::UPLOADS_DIRECTORY)
            ->willReturn($uploadsDirectory);

        $service = new JobMetadataService($config);

        $job = new ESTGenerateBlastJob();
        $job->setJobFilename($jobFilename);
        $job->setAllByAllBlastEValue(Constants::DEFAULT_ALL_BY_ALL_BLAST_EVALUE);
        $job->setBlastEValue(Constants::DEFAULT_BLAST_EVALUE);
        $job->setMaxBlastSequences(Constants::DEFAULT_MAX_BLAST_SEQUENCES);
        $job->setExcludeFragments(false);

        $metadata = $service->collectMetadata($job);

        $sequenceItem = null;
        foreach ($metadata as $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'sequence_preview') {
                $sequenceItem = $item;
                break;
            }
        }

        $this->assertNotNull($sequenceItem);
        $this->assertSame('Sequence', $sequenceItem['label']);
        $this->assertStringStartsWith('"><script>alert(1)', $sequenceItem['preview']);
        $this->assertStringContainsString('<script>alert(1)</script>', $sequenceItem['tooltip']);

        foreach ($metadata as $item) {
            if (is_string($item)) {
                $this->assertStringNotContainsString('<span', $item);
            }
        }

        unlink($uploadsDirectory . '/' . $jobFilename);
        rmdir($uploadsDirectory);
    }
}
