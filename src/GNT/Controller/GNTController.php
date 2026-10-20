<?php

namespace App\GNT\Controller;

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
use App\Service\ParentTransferService;
use App\Service\ResultFileDownloadService;
use App\ViewModel\Factory\HomePageViewModelFactory;
use App\ViewModel\Factory\GNTGNNViewModelFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

class GNTController extends AbstractController
{
    public const DOWNLOAD_ROUTE_NAME = 'gnn_results_download';
    public const DATASET_DOWNLOAD_ROUTE_NAME = 'gnn_results_summary';
    public const DATASET_RESULTS_ROUTE_NAME = 'gnn_results';

    public function __construct(
        private readonly JobFormFactoryService $jobFormFactory,
        private readonly JobFormSubmissionService $submissionService,
        private readonly JobRetrieverService $jobService,
        private readonly JobStatusSubmissionHandlerService $submissionEmailDispatcher,
    )
    {
    }

    #[Route('/efi-gnt', name: 'gnt_home')]
    #[RateLimit(limiter: 'job_submission')]
    public function gnt(
        Request $request,
        HomePageViewModelFactory $viewModelFactory,
        ParentTransferService $transferService,
    ): Response
    {
        $targetForm = $request->query->get('f') ?? '';

        $gntViewModel = $viewModelFactory->create(SummaryJobType::GNT, $request->query);
        $gndViewModel = $viewModelFactory->create(SummaryJobType::GND, $request->query);

        // Add the GNT and GND view models for displaying previous jobs, etc
        $formViews['gnt_page'] = $gntViewModel;
        $formViews['gnd_page'] = $gndViewModel;

        // Only create and handle forms if the user has permission for that
        if ($this->isGranted(SubmissionVoter::JOB_SUBMISSION)) {
            $transferRequestOptions = $transferService->getParentTransferRequestOptions($request);
            $formMap = $this->createAllForms($transferRequestOptions ?? []);

            // Validate and set the active form
            if ($targetForm && isset($formMap[$targetForm]) && $transferRequestOptions) {
                $gntViewModel->activeForm = $targetForm;
            }

            // Create the forms that will be displayed on the home page
            foreach ($formMap as $name => $data) {
                $formViews[$name] = $data->form;

                // Only handle the form that was actually submitted
                if ($targetForm && $targetForm !== $name) {
                    continue;
                }

                $handled = $this->submissionService->handleFormAndSetFlash($data);
                if ($handled) {
                    $this->submissionEmailDispatcher->queue($data->job);
                    return $this->redirectToRoute('gnt_home');
                }
            }
        }

        return $this->render('efi-gnt/home.html.twig', $formViews);
    }

    private function createAllForms(array $parentOptions): array
    {
        $sequenceIdFormData = null;
        // If there was a transfer request, then add the suggested name to the job form
        if (isset($parentOptions['sequenceIdForm']['parent_sequences'])) {
            $sequenceIdFormData = [];
            $sequenceIdFormData['jobName'] = $parentOptions['sequenceIdForm']['suggested_name'] ?? null;
            $sequenceIdFormData['textUniprot'] = $parentOptions['sequenceIdForm']['parent_sequences'] ?? null;
            $sequenceIdFormData['unirefVersion'] = $parentOptions['sequenceIdForm']['parent_seq_type'] ?? null;
        }

        $showActiveDatabases = ! isset($parentOptions['gnnForm']);

        $formMap = [];
        $formMap['gnnForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'gnt_home',
                'gnnForm',
                \App\Form\GNTGNNForm::class,
                \App\Entity\GNTGNNJob::class,
                'GND BLAST',
                array_merge([
                    'show_active_databases' => $showActiveDatabases,
                ], $parentOptions['gnnForm'] ?? [])
            ),
            JobFormSubmissionHandlerType::Uploaded
        );
        $formMap['blastForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'gnt_home',
                'blastForm',
                \App\Form\GNTDiagramBlastForm::class,
                \App\Entity\GNTDiagramBlastJob::class,
                'GND BLAST',
                [
                    'show_blast_params' => true,
                ]
            ),
            JobFormSubmissionHandlerType::StringToFile
        );
        $formMap['sequenceIdForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'gnt_home',
                'sequenceIdForm',
                \App\Form\GNTDiagramSequenceIdForm::class,
                \App\Entity\GNTDiagramSequenceIdJob::class,
                'GND IDs',
                ['show_blast_params' => false],
                $sequenceIdFormData,
            ),
            JobFormSubmissionHandlerType::StringOrFile
        );
        $formMap['fastaForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'gnt_home',
                'fastaForm',
                \App\Form\GNTDiagramFastaForm::class,
                \App\Entity\GNTDiagramFastaJob::class,
                'GND FASTA',
                []
            ),
            JobFormSubmissionHandlerType::StringOrFile
        );
        $formMap['viewGndForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'gnt_home',
                'viewGndForm',
                \App\Form\GNTViewDiagramForm::class,
                \App\Entity\GNTViewDiagramJob::class,
                'View Saved Diagrams',
                []
            ),
            JobFormSubmissionHandlerType::Uploaded
        );

		return $formMap;
    }

    #[Route('/efi-gnt/results/{id}/{key}', name: self::DATASET_RESULTS_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function gnn_results(
        int $id,
        Uuid $key,
        GNTGNNViewModelFactory $viewModelFactory
    ): Response
    {
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $viewModel = $viewModelFactory->create($job);

        return $this->render('efi-gnt/results.html.twig', [
            'page' => $viewModel,
            'page_job_id' => $id,
        ]);
    }

    #[Route('/efi-gnt/results/{id}/{key}/summary', name: self::DATASET_DOWNLOAD_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function gnn_results_summary(
        int $id,
        Uuid $key,
        JobSummaryService $summaryService,
        ResultFileDownloadService $downloadService
    ): Response
    {
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $summaryData = $summaryService->getDownloadSummaryForJob($job);
        $textDownloadResponse = $downloadService->createDownloadTextResponse($job, $summaryData, 'gnn_summary');

        return $textDownloadResponse;
    }

    #[Route('/efi-gnt/results/{id}/{key}/download/{fileType}', name: self::DOWNLOAD_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6, 'fileType' => '[a-zA-Z0-9_]+'], methods: ['GET'])]
    public function gnn_results_download(
        int $id,
        Uuid $key,
        string $fileType,
        ResultFileDownloadService $downloadService
    ): Response
    {
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $downloadResponse = $downloadService->createDownloadResponse($job, $fileType);
        return $downloadResponse;
    }
}
