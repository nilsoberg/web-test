<?php

namespace App\EST\Controller;

use App\Attribute\RateLimit;
use App\Auth\Voter\SubmissionVoter;
use App\Config\Settings;
use App\Dto\JobFormFactoryData;
use App\Dto\JobFormHandlerData;
use App\Entity\ESTGenerateBlastJob;
use App\Entity\Job;
use App\Enum\JobFormSubmissionHandlerType;
use App\Service\ConfigurationManager;
use App\Service\JobFormFactoryService;
use App\Service\JobFormSubmissionService;
use App\Service\JobRetrieverService;
use App\Service\JobSummaryService;
use App\Service\ResultFileDownloadService;
use App\Service\ResultFileService;
use App\Service\SunburstDataRetrievalService;
use App\ViewModel\Factory\ESTGenerateResultsViewModelFactory;
use App\ViewModel\Factory\ESTSSNFinalizationViewModelFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/efi-est/results')]
class ESTResultController extends AbstractController
{
    public const FILE_ROUTE_NAME = 'efi_est_analysis_file';
    public const DATASET_RESULTS_ROUTE_NAME = 'efi_est_dataset_results';
    public const SSN_RESULTS_ROUTE_NAME = 'efi_est_analysis_results';

    public function __construct(
        private readonly ConfigurationManager $configManager,
        private readonly JobFormFactoryService $jobFormFactory,
        private readonly JobFormSubmissionService $submissionService,
        private readonly JobRetrieverService $jobRetrieverService,
        private readonly ResultFileDownloadService $downloadService,
    )
    {
    }

    // The dataset (e.g. EST Generate) page
	#[Route('/dataset/{id}/{key}', name: self::DATASET_RESULTS_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET', 'POST'])]
    #[RateLimit(limiter: 'job_submission')]
    public function efi_est_dataset_results(
        Request $request,
        int $id,
        Uuid $key,
        ESTGenerateResultsViewModelFactory $viewModelFactory,
    ): Response
    {
        /** @var UserEntity $user */
        $user = $this->getUser();
        $showDevFeatures = $user ? $user->isDevFeaturesEnabled() : false;

        // Find the job
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $ssnFinalizationForm = null;

        // Only create and handle forms if the user has permission for that
        if ($this->isGranted(SubmissionVoter::JOB_SUBMISSION)) {
            // Create the form
            $handlerData = $this->createSsnFinalizationForm($job, $showDevFeatures);

            // Handle submission
            $handled = $this->submissionService->handleFormAndSetFlash($handlerData, 'SSN finalization started successfully');
            if ($handled) {
                return $this->redirectToRoute('est_home');
            }

            $ssnFinalizationForm = $handlerData->form->createView();
        }

        // If the form wasn't submitted or was invalid, display it again
        $pageViewModel = $viewModelFactory->create($job);

        return $this->render('efi-est/dataset.html.twig', [
            'page' => $pageViewModel,
            'page_job_id' => $id,
            'ssnFinalizationForm' => $ssnFinalizationForm,
        ]);
    }

    // Get data for displaying in sunburst
    #[Route('/sunburst/{id}/{key}/data', name: 'efi_est_sunburst_data', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function get_sunburst_data(
        int $id,
        Uuid $key,
        SunburstDataRetrievalService $sunburstService
    ): JsonResponse
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $sunburstData = $sunburstService->getData($job);

        return new JsonResponse($sunburstData);
    }

    // Download fasta from selected sunburst
    #[Route('/sunburst/{id}/{key}/fasta', name: 'efi_est_sunburst_fasta', requirements: ['key' => Requirement::UUID_V6], methods: ['POST'])]
    public function get_sunburst_fasta(
        Request $request,
        int $id,
        Uuid $key,
        SunburstDataRetrievalService $sunburstService
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];

        $downloadSuffix = $sunburstService->getDownloadFilenameSuffix($data['o'], $data['it']);
        $downloadResponse = $sunburstService->getFastaByIds($job, $ids, $downloadSuffix);

        return $downloadResponse;
    }

    // Get edges for graph on dataset page
    #[Route('/dataset-edges/{id}/{key}', name: 'efi_est_dataset_edges', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function get_edges_tab_data(
        int $id,
        Uuid $key,
        ResultFileService $fileService
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $tabFile = $fileService->getResultFilePath($job, 'est_edges');
        if ($tabFile === null) {
            $tabData = "";
        } else {
            $tabData = file_get_contents($tabFile);
        }

        return new Response(
            $tabData,
            Response::HTTP_OK,
            ['content-type' => 'text/plain']
        );
    }

    // Download blast sequence for blast jobs
    #[Route('/blast-sequence/{id}/{key}', name: 'efi_est_blast_sequence', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function get_blast_sequence(
        int $id,
        Uuid $key
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        if (!$job instanceof ESTGenerateBlastJob) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $blastSequence = $this->loadBlastSequence($job);

        return new Response(
            $blastSequence,
            Response::HTTP_OK,
            ['content-type' => 'text/plain']
        );
    }

    // Download generate summary table
    #[Route('/dataset/{id}/{key}/summary', name: 'efi_est_dataset_summary', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function dataset_summary(
        int $id,
        Uuid $key,
        JobSummaryService $summaryService
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $summaryData = $summaryService->getDownloadSummaryForJob($job);
        $textDownloadResponse = $this->downloadService->createDownloadTextResponse($job, $summaryData, 'est_dataset_summary');

        return $textDownloadResponse;
    }

    // Download generate image
    #[Route('/dataset/{id}/{key}/image/{fileType}', name: 'efi_est_dataset_image', requirements: ['key' => Requirement::UUID_V6, 'fileType' => '[a-zA-Z0-9_]+'], methods: ['GET'])]
    public function dataset_image(
        Request $request,
        int $id,
        Uuid $key,
        string $fileType
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        if ($request->query->has('download')) {
            $imageResponse = $this->downloadService->createDownloadResponse($job, $fileType);
        } else {
            $imageResponse = $this->downloadService->createDisplayResponse($job, $fileType);
        }

        return $imageResponse;
    }

    // Display analysis (e.g. EST Analysis) results page
    #[Route('/analysis/{id}/{key}', name: self::SSN_RESULTS_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function analysis_results(
        int $id,
        Uuid $key,
        ESTSSNFinalizationViewModelFactory $viewModelFactory
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $parentJob = $job->getParentJob();
        if (!$parentJob) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $pageViewModel = $viewModelFactory->create($job);

        return $this->render('efi-est/analysis.html.twig', [
            'page_job_id' => $id,
            'page' => $pageViewModel,
        ]);
    }

    // Download summary table
    #[Route('/analysis/{id}/{key}/summary', name: 'efi_est_analysis_summary', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function analysis_summary(
        int $id,
        Uuid $key,
        JobSummaryService $summaryService
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $parentJob = $job->getParentJob();
        if (!$parentJob) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $summaryData = $summaryService->getDownloadSummaryForJob($job);
        $textDownloadResponse = $this->downloadService->createDownloadTextResponse($job, $summaryData, 'est_analysis_summary');

        return $textDownloadResponse;
    }

    // Download SSN
    #[Route('/analysis/{id}/{key}/file/{fileType}', name: self::FILE_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6, 'fileType' => '[a-zA-Z0-9_]+'], methods: ['GET'])]
    public function analysis_file(
        int $id,
        Uuid $key,
        string $fileType,
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $parentJob = $job->getParentJob();
        if (!$parentJob) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $downloadResponse = $this->downloadService->createDownloadResponse($job, $fileType);
        return $downloadResponse;
    }




    private function loadBlastSequence(ESTGenerateBlastJob $job): string
    {
        $filename = $job->getJobFilename();
        $path = $this->configManager->get(Settings::UPLOADS_DIRECTORY) . '/' . $filename;
        $blastSequence = file_get_contents($path);
        return $blastSequence;
    }

    private function createSsnFinalizationForm(Job $parentJob, bool $showDevFeatures): JobFormHandlerData
    {
        return $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                null,
                'ssnFinalizationForm',
                \App\Form\ESTSSNFinalizationForm::class,
                \App\Entity\ESTSSNFinalizationJob::class,
                'SSN Finalization',
                [
                    'default_ssn_name' => $parentJob->getJobName(),
                    'parent_id' => $parentJob->getId(),
                    'parent_key' => $parentJob->getUuid(),
                    'show_dev_features' => $showDevFeatures,
                ]
            ),
            JobFormSubmissionHandlerType::None,
            function (Job $job) use ($parentJob) {
                $job->setJobName($parentJob->getJobName());
            },
        );
    }
}
