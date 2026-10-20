<?php

namespace App\Tests\Controller\SmokeTest;

use App\Repository\JobRepository;
use App\Tests\BaseSmokeTestCase;
use App\Tests\Story\CompletedESTDatasetCreationStory;
use Symfony\Component\Routing\RouterInterface;

class ESTGenerateFamiliesSmokeTest extends BaseSmokeTestCase
{
    public function testESTDatasetCreationPageLoads(): void
    {
        $client = static::createClient();
		$client->catchExceptions(false);

        CompletedESTDatasetCreationStory::load();

        $client->loginUser($this->getTestUser());

        $job = $this->getContainer()->get(JobRepository::class)->findOneBy(['jobName' => 'Rapid Test Analysis']);

        $router = static::getContainer()->get(RouterInterface::class);
        
        $url = $router->generate('efi_est_dataset_results', [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
        ]);

        $client->request('GET', $url);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Rapid Test Analysis');
    }
}
