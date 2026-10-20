<?php

namespace App\ViewModel\Factory;

use App\Entity\DisplayName;
use App\Entity\DomainBoundariesTrait;
use App\Entity\ESTGenerateBlastJob;
use App\Entity\ESTGenerateFastaJob;
use App\Entity\ESTGenerateJob;
use App\Entity\Job;
use App\Enum\JobStatus;
use App\Enum\SequenceType;
use App\Service\JobSummaryService;
use App\Service\ResultFileDownloadService;
use App\Service\ResultFileService;
use App\Service\SunburstDescriptionService;
use App\ViewModel\ESTGenerateResultsViewModel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ESTGenerateResultsViewModelFactory
{
    public function __construct(
        private readonly JobSummaryService $summaryService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ResultFileDownloadService $downloadService,
        private readonly ResultFileService $fileService,
        private readonly SunburstDescriptionService $sunburstDescriptionService,
    )
	{
    }

    public function create(Job $job): ESTGenerateResultsViewModel
    {
        $jobTraits = class_uses($job);
        $isDomain = in_array(DomainBoundariesTrait::class, $jobTraits) && $job->getDomain();
        $unirefVersion = $job->getUnirefVersion();

        $summaryData = $this->summaryService->getDisplaySummaryForJob($job);
        $createdSsns = $this->getCreatedSsns($job);
        $sunburstParams = $this->getSunburstParams($job);
        $resultImages = $this->getGenerateResultImages($job, $unirefVersion, $isDomain);
        $resultPlots = $this->getGenerateResultPlots($job, $unirefVersion, $isDomain);

        $edgesTabFile = $this->fileService->getResultFilePath($job, 'est_edges', false);
        if (!file_exists($edgesTabFile)) {
            $edgesTabUrl = "";
        } else {
            $edgesTabUrl = $this->urlGenerator->generate('efi_est_dataset_edges', [
                'id' => $job->getId(),
                'key' => $job->getUuid()
            ]);
        }

        $summaryDownloadUrl = $this->urlGenerator->generate('efi_est_dataset_summary', [
            'id' => $job->getId(),
            'key' => $job->getUuid()
        ]);

        return new ESTGenerateResultsViewModel(
            jobType: DisplayName::getFrom($job),
            jobName: $job->getJobName(),
            id: $job->getId(),
            key: $job->getUuid(),
            isDomain: $isDomain,
            useFamilies: $job->getAddFamilies() ?? false,
            unirefVersion: ($unirefVersion ?? SequenceType::UniProt)->getLabel(),
            summaryData: $summaryData,
            summaryDownloadUrl: $summaryDownloadUrl,
            resultImages: $resultImages,
            resultPlots: $resultPlots,
            createdSsns: $createdSsns,
            edgesTabUrl: $edgesTabUrl,
            sunburstParams: $sunburstParams,
        );
    }
    
    private function getCreatedSsns(ESTGenerateJob $job)
    {
        $ssns = [];
        foreach ($job->getChildJobs() as $child) {
            if ($child->getStatus() !== JobStatus::Finished) {
                continue;
            }

            $route = $this->urlGenerator->generate('efi_est_analysis_results', ['id' => $child->getId(), 'key' => $child->getUuid()]);
            $title = sprintf("%s AS=%s (Analysis ID=%s)",
                $child->getJobName(),
                $child->getThresholdValue(),
                $child->getId()
            );
            $ssns[] = ['route' => $route, 'title' => $title];
        }
        return $ssns;
    }

    private function getGenerateResultImages(Job $job, ?SequenceType $unirefVersion, bool $isDomain): array
    {
        $lengthHistogramFileType = 'length_histogram_uniprot';
        $resultImages['length_histogram_uniprot'] = $this->urlGenerator->generate('efi_est_dataset_image', [
                'id' => $job->getId(),
                'key' => $job->getUuid(),
                'fileType' => $lengthHistogramFileType
            ]);

        $resultImages['alignment_histogram'] = $this->urlGenerator->generate('efi_est_dataset_image', [
                'id' => $job->getId(),
                'key' => $job->getUuid(),
                'fileType' => 'alignment_length'
            ]);
        $resultImages['percent_identity'] = $this->urlGenerator->generate('efi_est_dataset_image', [
                'id' => $job->getId(),
                'key' => $job->getUuid(),
                'fileType' => 'percent_identity'
            ]);
        $resultImages['number_of_edges'] = $this->urlGenerator->generate('efi_est_dataset_image', [
                'id' => $job->getId(),
                'key' => $job->getUuid(),
                'fileType' => 'number_of_edges'
            ]);

        if ($unirefVersion !== SequenceType::UniProt) {
            $fileType = $this->getUnirefFileSuffix($unirefVersion);
            $domainFileType = "{$fileType}_domain";
            $resultImages[$fileType] = $this->urlGenerator->generate('efi_est_dataset_image', [
                    'id' => $job->getId(),
                    'key' => $job->getUuid(),
                    'fileType' => $fileType,
                ]);
            if ($isDomain) {
                $resultImages[$domainFileType] = $this->urlGenerator->generate('efi_est_dataset_image', [
                        'id' => $job->getId(),
                        'key' => $job->getUuid(),
                        'fileType' => $domainFileType,
                    ]);
            }
        }

        if ($isDomain) {
            $resultImages['length_histogram_uniprot_domain'] = $this->urlGenerator->generate('efi_est_dataset_image', [
                    'id' => $job->getId(),
                    'key' => $job->getUuid(),
                    'fileType' => 'length_histogram_uniprot_domain'
                ]);
        }

        return $resultImages;
    }

    private function getGenerateResultPlots(Job $job, ?SequenceType $unirefVersion, bool $isDomain): array
    {
        $resultPlots = [
            'length_histogram_uniprot' => $this->generateResultFileUrlIfExists($job, 'length_histogram_uniprot_json'),
            'alignment_histogram' => $this->generateResultFileUrlIfExists($job, 'alignment_length_json'),
            'percent_identity' => $this->generateResultFileUrlIfExists($job, 'percent_identity_json'),
            'number_of_edges' => $this->generateResultFileUrlIfExists($job, 'number_of_edges_json'),
        ];

        $fileType = $this->getUnirefFileSuffix($unirefVersion);
        $domainFileType = "{$fileType}_domain";

        if ($unirefVersion !== SequenceType::UniProt) {
            $resultPlots[$fileType] = $this->generateResultFileUrlIfExists($job, "{$fileType}_json");
            if ($isDomain) {
                $resultPlots[$domainFileType] = $this->generateResultFileUrlIfExists($job, "{$domainFileType}_json");
            }
        }

        if ($isDomain) {
            $resultPlots['length_histogram_uniprot_domain'] = $this->generateResultFileUrlIfExists($job, 'length_histogram_uniprot_domain_json');
        }

        return $resultPlots;
    }

    private function generateResultFileUrlIfExists(Job $job, string $fileType): ?string
    {
        $resultPath = $this->fileService->getResultFilePath($job, $fileType, false);
        if (!file_exists($resultPath)) {
            return null;
        }

        return $this->urlGenerator->generate('efi_est_dataset_image', [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'fileType' => $fileType,
        ]);
    }

    private function getSunburstParams(Job $job): array
    {
        $sunburstDescText = $this->sunburstDescriptionService->getSunburstDescription($job);

        $primaryIdType = $job->getUnirefVersion() ?? SequenceType::UniProt;

        $sunburstParams = $this->sunburstDescriptionService->getSunburstParameters(
            job: $job,
            dataUrlRoute: 'efi_est_sunburst_data',
            fastaUrlRoute: 'efi_est_sunburst_fasta',
            sunburstDescText: $sunburstDescText,
            idType: $primaryIdType,
        );

        return $sunburstParams;
    }

    /**
     * Returns a suffix for the job sequence type that maps to the appropriate file type in
     * custom_file_types.yaml.
     * @param {?SequenceType} $unirefVersion
     * @return {string} one of 'uniprot', 'uniref50', 'uniref90'
     */
    private function getUnirefFileSuffix(?SequenceType $unirefVersion): string
    {
        if ($unirefVersion === null) {
            return 'uniprot';
        }

        $unirefVal = match ($unirefVersion) {
            SequenceType::UniRef50 => 'uniref50',
            SequenceType::UniRef90 => 'uniref90',
            SequenceType::UniProt => 'uniprot',
        };

        $fileType = "length_histogram_{$unirefVal}";

        return $fileType;
    }
}
