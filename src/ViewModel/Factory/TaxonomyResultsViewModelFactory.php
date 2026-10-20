<?php

namespace App\ViewModel\Factory;

use App\Entity\DisplayName;
use App\Entity\Job;
use App\Entity\TaxonomyAccessionJob;
use App\Entity\TaxonomyFamiliesJob;
use App\Enum\SequenceType;
use App\Service\JobSummaryService;
use App\Service\ResultFileDownloadService;
use App\Service\ResultFileService;
use App\Service\SunburstDescriptionService;
use App\ViewModel\TaxonomyResultsViewModel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TaxonomyResultsViewModelFactory
{
    public function __construct(
        private readonly JobSummaryService $summaryService,
        private readonly ResultFileDownloadService $downloadService,
        private readonly ResultFileService $fileService,
        private readonly SunburstDescriptionService $sunburstDescriptionService,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
	{
    }

    public function create(Job $job): TaxonomyResultsViewModel
    {
        $summaryData = $this->summaryService->getDisplaySummaryForJob($job);
        $sunburstParams = $this->getSunburstParams($job);
        $resultImages = $this->getGenerateResultImages($job);

        $summaryDownloadUrl = $this->urlGenerator->generate('taxonomy_summary', [
            'id' => $job->getId(),
            'key' => $job->getUuid()
        ]);

        return new TaxonomyResultsViewModel(
            jobType: DisplayName::getFrom($job),
            jobName: $job->getJobName(),
            id: $job->getId(),
            key: $job->getUuid(),
            summaryData: $summaryData,
            summaryDownloadUrl: $summaryDownloadUrl,
            resultImages: $resultImages,
            sunburstParams: $sunburstParams
        );
    }
    
    private function getGenerateResultImages(Job $job): array
    {
        $resultImages['length_histogram_uniprot'] = $this->urlGenerator->generate('taxonomy_result_image', [
                'id' => $job->getId(),
                'key' => $job->getUuid(),
                'fileType' => 'taxonomy_length_histogram_uniprot',
            ]);

        $resultImages['length_histogram_uniref90'] = $this->urlGenerator->generate('taxonomy_result_image', [
                'id' => $job->getId(),
                'key' => $job->getUuid(),
                'fileType' => 'taxonomy_length_histogram_uniref90',
            ]);
        $resultImages['length_histogram_uniref50'] = $this->urlGenerator->generate('taxonomy_result_image', [
                'id' => $job->getId(),
                'key' => $job->getUuid(),
                'fileType' => 'taxonomy_length_histogram_uniref50',
            ]);

        return $resultImages;
    }

    private function getSunburstParams(Job $job): array
    {
        if ($job instanceof TaxonomyAccessionJob || $job instanceof TaxonomyFamiliesJob) {
            $primaryIdType = $job->getUnirefVersion() ?? SequenceType::UniProt;
        } else {
            $primaryIdType = SequenceType::UniProt;
        }

        $sunburstDescText = $this->sunburstDescriptionService->getSunburstDescription($job);

        $sunburstParams = $this->sunburstDescriptionService->getSunburstParameters(
            job: $job,
            dataUrlRoute: 'taxonomy_sunburst_data',
            fastaUrlRoute: 'taxonomy_sunburst_fasta',
            sunburstDescText: $sunburstDescText,
            idType: $primaryIdType,
        );

        return $sunburstParams;
    }
}
