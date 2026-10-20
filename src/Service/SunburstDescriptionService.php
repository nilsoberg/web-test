<?php

namespace App\Service;

use App\Entity\Job;
use App\Enum\SequenceType;
use App\Service\ResultFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SunburstDescriptionService
{
    public function __construct(
        private readonly ResultFileService $fileService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly array $sunburstDescriptions,
    )
	{
    }

    public function getSunburstDescription(Job $job): ?string
    {
        $shortName = (new \ReflectionClass($job))->getShortName();
        return $this->sunburstDescriptions[$shortName] ?? null;
    }

    /**
     * Get the parameters necessary for the sunburst Stimulus controller/JavaScript app.
     * @param {string} $sunburstDescText - the description text to use (usually tied to the job, but
     *     can originate from the parent job in some cases, which is why it's passed separately)
     */
    public function getSunburstParameters(Job $job, string $dataUrlRoute, string $fastaUrlRoute, string $sunburstDescText, SequenceType $idType): array
    {
        $unirefVersion = 50;

        $dataUrl = $this->urlGenerator->generate($dataUrlRoute, ['id' => $job->getId(), 'key' => $job->getUuid()]);
        $fastaUrl = $this->urlGenerator->generate($fastaUrlRoute, ['id' => $job->getId(), 'key' => $job->getUuid()]);
        $estTransferUrl = $this->urlGenerator->generate('est_home', ['f' => 'estAccessionForm'], UrlGeneratorInterface::ABSOLUTE_URL);
        $gntTransferUrl = $this->urlGenerator->generate('gnt_home', ['f' => 'sequenceIdForm'], UrlGeneratorInterface::ABSOLUTE_URL);

        $baseFilename = $this->fileService->getDownloadFilenameFromJob($job, 'sunburst_id_list', 'txt');

        $sunburstParams = array(
            'primary_id_type' => $idType->getLabel(),
            'uniref_version' => $unirefVersion,
            'description_text' => $sunburstDescText,
            'data_url' => $dataUrl,
            'fasta_url' => $fastaUrl,
            'est_transfer_url' => $estTransferUrl,
            'gnt_transfer_url' => $gntTransferUrl,
            'base_filename' => $baseFilename,
        );

        return $sunburstParams;
    }
}
