<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class JobMetadataRenderingTest extends TestCase
{
    public function testMetadataPartialEscapesSequencePreviewContent(): void
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 2) . '/templates');
        $twig = new Environment($loader);

        $html = $twig->render('components/_job_metadata.html.twig', [
            'metadata' => [[
                'type' => 'sequence_preview',
                'label' => 'Sequence',
                'preview' => '\"><img src=x onerror=alert(1)>...',
                'tooltip' => '\"><script>alert(1)</script>',
            ]],
        ]);

        $this->assertStringContainsString('data-bs-toggle="tooltip"', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;img src=x onerror=alert(1)&gt;...', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }
}
