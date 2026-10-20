<?php

namespace App\CGFP\Controller;

use App\Attribute\RateLimit;
use App\Auth\Voter\SubmissionVoter;
use App\Dto\JobFormFactoryData;
use App\Dto\JobFormHandlerData;
use App\Entity\Job;
use App\Entity\MetagenomeMetadata;
use App\Enum\DatabaseMetadataType;
use App\Enum\JobFormSubmissionHandlerType;
use App\Enum\SummaryJobType;
use App\Repository\JobRepository;
use App\Service\DatabaseListService;
use App\Service\JobFormFactoryService;
use App\Service\JobFormSubmissionService;
use App\Service\JobRetrieverService;
use App\Service\JobStatusSubmissionHandlerService;
use App\Service\JobSummaryService;
use App\Service\ParentTransferService;
use App\Service\ResultFileDownloadService;
use App\ViewModel\Factory\CGFPResultsViewModelFactory;
use App\ViewModel\Factory\HomePageViewModelFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/efi-cgfp')]
class CGFPController extends AbstractController
{
    public const DOWNLOAD_ROUTE_NAME = 'cgfp_results_download';
    public const IDENTIFY_DATASET_DOWNLOAD_ROUTE_NAME = 'cgfp_identify_results_summary';
    public const QUANTIFY_DATASET_DOWNLOAD_ROUTE_NAME = 'cgfp_quantify_results_summary';
    public const IDENTIFY_ROUTE_NAME = 'cgfp_identify';
    public const QUANTIFY_ROUTE_NAME = 'cgfp_quantify';

    public function __construct(
        private readonly JobFormFactoryService $jobFormFactory,
        private readonly JobFormSubmissionService $submissionService,
        private readonly JobRetrieverService $jobService,
        private readonly JobStatusSubmissionHandlerService $submissionEmailDispatcher,
    )
    {
    }

    #[Route('/', name: 'cgfp_home')]
    #[RateLimit(limiter: 'job_submission')]
    public function home(
        Request $request,
        HomePageViewModelFactory $viewModelFactory,
        ParentTransferService $transferService,
    ): Response
    {
        $targetForm = $request->query->get('f') ?? '';

        /** @var UserEntity $user */
        $user = $this->getUser();

        $viewModel = $viewModelFactory->create(SummaryJobType::CGFP, $request->query);

        $formViews['page'] = $viewModel;

        if ($this->isGranted(SubmissionVoter::JOB_SUBMISSION)) {
            $transferRequestOptions = $transferService->getParentTransferRequestOptions($request);
            $formMap = $this->createAllForms($transferRequestOptions ?? []);

            if (isset($formMap[$targetForm]) && $transferRequestOptions) {
                $viewModel->activeForm = $targetForm;
            }

            foreach ($formMap as $name => $data) {
                $formViews[$name] = $data->form;

                $handled = $this->submissionService->handleFormAndSetFlash($data);

                if ($handled) {
                    $this->submissionEmailDispatcher->queue($data->job);
                    // A job was successfully created, redirect immediately
                    return $this->redirectToRoute('cgfp_home');
                }
            }
        }

        return $this->render('efi-cgfp/home.html.twig', $formViews);
    }

    #[Route('/identify/{id}/{key}', name: self::IDENTIFY_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET', 'POST'])]
    #[RateLimit(limiter: 'job_submission')]
    public function identify(
        Request $request,
        int $id,
        Uuid $key,
        CGFPResultsViewModelFactory $viewModelFactory,
        DatabaseListService $databaseService,
    ): Response
    {
        // Find the job
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $databases = $databaseService->getActiveDatabases(DatabaseMetadataType::Metagenome);

        $form = null;

        if ($this->isGranted(SubmissionVoter::JOB_SUBMISSION)) {
            $handlerData = $this->createQuantifyForm($job, $databases);

            // Handle submission
            $handled = $this->submissionService->handleFormAndSetFlash($handlerData, 'CGFP Quantification submitted successfully');
            if ($handled) {
                return $this->redirectToRoute('cgfp_home');
            }

            $form = $handlerData->form->createView();
        }

        // If the form wasn't submitted or was invalid, display it again
        $pageViewModel = $viewModelFactory->createIdentify($job);

        return $this->render('efi-cgfp/identify.html.twig', [
            'page' => $pageViewModel,
            'page_job_id' => $id,
            'form' => $form,
        ]);
    }

    #[Route('/api/metagenomes', name: 'api_database_metagenomes', methods: ['GET'])]
    public function get_metagenomes(
        Request $request,
        DatabaseListService $databaseService,
    ): JsonResponse
    {
        $dbId = $request->query->get('db');
        $database = $databaseService->getDatabase($dbId);

        if (!$database || ! ($database instanceof MetagenomeMetadata)) {
            return new JsonResponse([], 400);
        }

        $metagenomes = $database->getMetagenomes();

        // Returns array: [['id' => 'SRS011263', 'region' => 'anterior nares', 'gender' => 'Female'], ...]
        return new JsonResponse($metagenomes ?? []);
    }

    private function createQuantifyForm(Job $parentJob, array $databases): JobFormHandlerData
    {
        return $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                null,
                'cgfpQuantifyForm',
                \App\Form\CGFPQuantifyForm::class,
                \App\Entity\CGFPQuantifyJob::class,
                'CGFP Quantification',
                [
                    'default_ssn_name' => $parentJob->getJobName(),
                    'parent_id' => $parentJob->getId(),
                    'parent_key' => $parentJob->getUuid(),
                    'active_databases' => $databases,
                ]
            ),
            JobFormSubmissionHandlerType::None,
            function (Job $job) use ($parentJob) {
                $job->setJobName($parentJob->getJobName());
            },
        );
    }

    #[Route('/quantify/{id}/{key}/results', name: self::QUANTIFY_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function quantify(
        Request $request,
        int $id,
        Uuid $key,
        CGFPResultsViewModelFactory $viewModelFactory,
    ): Response
    {
        // Find the job
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        // If the form wasn't submitted or was invalid, display it again
        $pageViewModel = $viewModelFactory->createQuantify($job);

        return $this->render('efi-cgfp/quantify.html.twig', [
            'page' => $pageViewModel,
            'page_job_id' => $id,
        ]);
    }

    #[Route('/identify/{id}/{key}/summary', name: self::IDENTIFY_DATASET_DOWNLOAD_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function identify_results_summary(
        int $id,
        Uuid $key,
        JobSummaryService $summaryService,
        ResultFileDownloadService $downloadService,
    ): Response
    {
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $summaryData = $summaryService->getDownloadSummaryForJob($job);
        $textDownloadResponse = $downloadService->createDownloadTextResponse($job, $summaryData, 'identify_summary');

        return $textDownloadResponse;
    }

    #[Route('/quantify/{id}/{key}/summary', name: self::QUANTIFY_DATASET_DOWNLOAD_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function quantify_results_summary(
        int $id,
        Uuid $key,
        JobSummaryService $summaryService,
        ResultFileDownloadService $downloadService,
    ): Response
    {
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $summaryData = $summaryService->getDownloadSummaryForJob($job);
        $textDownloadResponse = $downloadService->createDownloadTextResponse($job, $summaryData, 'quantify_summary');

        return $textDownloadResponse;
    }

    #[Route('/results/{id}/{key}/download/{fileType}', name: self::DOWNLOAD_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6, 'fileType' => '[a-zA-Z0-9_]+'], methods: ['GET'])]
    public function results_download(
        int $id,
        Uuid $key,
        string $fileType,
        ResultFileDownloadService $downloadService,
    ): Response
    {
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $downloadResponse = $downloadService->createDownloadResponse($job, $fileType);
        return $downloadResponse;
    }

    private function createAllForms(array $parentOptions): array
    {
        $showActiveDatabases = ! isset($parentOptions['identifyForm']);

        $formMap = [];
        $formMap['identifyForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'cgfp_home',
                'identifyForm',
                \App\Form\CGFPIdentifyForm::class,
                \App\Entity\CGFPIdentifyJob::class,
                'Identify',
                array_merge([
                    'show_active_databases' => $showActiveDatabases,
                ], $parentOptions['identifyForm'] ?? [])
            ),
            JobFormSubmissionHandlerType::Uploaded
        );
		return $formMap;
    }
}
