<?php

namespace App\Taxonomy\Controller;

use App\Attribute\RateLimit;
use App\Auth\Voter\SubmissionVoter;
use App\Dto\JobFormFactoryData;
use App\Enum\JobFormSubmissionHandlerType;
use App\Enum\SummaryJobType;
use App\Service\JobFormFactoryService;
use App\Service\JobFormSubmissionService;
use App\Service\JobRetrieverService;
use App\Service\JobStatusSubmissionHandlerService;
use App\Service\JobSummaryService;
use App\Service\ResultFileDownloadService;
use App\Service\SubmissionHandler\StringOrFileSubmissionHandler;
use App\Service\SunburstDataRetrievalService;
use App\ViewModel\Factory\HomePageViewModelFactory;
use App\ViewModel\Factory\TaxonomyResultsViewModelFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid; // For UUID validation

class TaxonomyController extends AbstractController
{
    public const DATASET_RESULTS_ROUTE_NAME = 'taxonomy_results';

    public function __construct(
        private readonly JobFormFactoryService $jobFormFactory,
        private readonly JobFormSubmissionService $submissionService,
        private readonly JobRetrieverService $jobRetrieverService,
        private readonly JobStatusSubmissionHandlerService $submissionEmailDispatcher,
        private readonly LoggerInterface $logger,
        private readonly ResultFileDownloadService $downloadService,
    )
    {
    }

    #[Route('/taxonomy', name: 'taxonomy_home')]
    #[RateLimit(limiter: 'job_submission')]
    public function taxonomy(
        Request $request,
        HomePageViewModelFactory $viewModelFactory,
    ): Response
    {
        $targetForm = $request->query->get('f') ?? '';

        $viewModel = $viewModelFactory->create(SummaryJobType::TAXONOMY, $request->query);

        $formViews['page'] = $viewModel;

        if ($this->isGranted(SubmissionVoter::JOB_SUBMISSION)) {
            $formMap = $this->createAllForms();

            // Validate and set the active form
            if ($targetForm && isset($formMap[$targetForm])) {
                $viewModel->activeForm = $targetForm;
            }

            foreach ($formMap as $name => $data) {
                $formViews[$name] = $data->form;
                $handled = $this->submissionService->handleFormAndSetFlash($data);
                if ($handled) {
                    $this->submissionEmailDispatcher->queue($data->job);
                    return $this->redirectToRoute('taxonomy_home');
                }
            }
        }

        return $this->render('taxonomy/home.html.twig', $formViews);
    }

    private function createAllForms(): array
    {
        $formMap = [];
        $formMap['familiesForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'taxonomy_home',
                'familiesForm',
                \App\Form\TaxonomyFamiliesForm::class,
                \App\Entity\TaxonomyFamiliesJob::class,
                'Families',
                []
            ),
            JobFormSubmissionHandlerType::Basic
        );
        $formMap['fastaForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'taxonomy_home',
                'fastaForm',
                \App\Form\TaxonomyFastaForm::class,
                \App\Entity\TaxonomyFastaJob::class,
                'FASTA',
                []
            ),
            JobFormSubmissionHandlerType::StringOrFile
        );
        $formMap['accessionForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'taxonomy_home',
                'accessionForm',
                \App\Form\TaxonomyAccessionForm::class,
                \App\Entity\TaxonomyAccessionJob::class,
                'Accession',
                []
            ),
            JobFormSubmissionHandlerType::StringOrFile
        );
		return $formMap;
    }

    // Display analysis results page
    #[Route('/taxonomy/results/{id}/{key}', name: self::DATASET_RESULTS_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function analysis_results(
        int $id,
        Uuid $key,
        TaxonomyResultsViewModelFactory $viewModelFactory
    ): Response
    {
        $job = $this->jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $pageViewModel = $viewModelFactory->create($job);

        return $this->render('taxonomy/results.html.twig', [
            'page' => $pageViewModel,
            'page_job_id' => $id,
        ]);
    }

    // Get data for displaying in sunburst
    #[Route('/taxonomy/results/{id}/{key}/sunburst/data', name: 'taxonomy_sunburst_data', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
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
    #[Route('/taxonomy/results/{id}/{key}/sunburst/fasta', name: 'taxonomy_sunburst_fasta', requirements: ['key' => Requirement::UUID_V6], methods: ['POST'])]
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

    // Download generate image
    #[Route('/taxonomy/results/{id}/{key}/image/{fileType}', name: 'taxonomy_result_image', requirements: ['key' => Requirement::UUID_V6, 'fileType' => '[a-zA-Z0-9_]+'], methods: ['GET'])]
    public function results_image(
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

    // Download summary table
    #[Route('/taxonomy/{id}/{key}/summary', name: 'taxonomy_summary', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function taxonomy_summary(
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
        $textDownloadResponse = $this->downloadService->createDownloadTextResponse($job, $summaryData, 'taxonomy_summary');

        return $textDownloadResponse;
    }

}
