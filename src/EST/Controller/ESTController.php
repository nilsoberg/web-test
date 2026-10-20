<?php

namespace App\EST\Controller;

use App\Attribute\RateLimit;
use App\Auth\Voter\SubmissionVoter;
use App\Dto\JobFormFactoryData;
use App\Enum\JobFormSubmissionHandlerType;
use App\Enum\SequenceType;
use App\Enum\SummaryJobType;
use App\Service\JobStatusSubmissionHandlerService;
use App\Service\JobFormFactoryService;
use App\Service\JobFormSubmissionService;
use App\Service\ParentTransferService;
use App\ViewModel\Factory\HomePageViewModelFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ESTController extends AbstractController
{
    public function __construct(
        private readonly JobFormFactoryService $jobFormFactory,
        private readonly JobFormSubmissionService $submissionService,
        private readonly JobStatusSubmissionHandlerService $submissionEmailDispatcher,
    )
    {
    }

    #[Route('/efi-est', name: 'est_home')]
    #[RateLimit(limiter: 'job_submission')]
    public function est_home(
        Request $request,
        HomePageViewModelFactory $viewModelFactory,
        ParentTransferService $transferService,
    ): Response
    {
        $targetForm = $request->query->get('f') ?? '';

        /** @var UserEntity $user */
        $user = $this->getUser();
        $showDevFeatures = $user ? $user->isDevFeaturesEnabled() : false;

        $viewModel = $viewModelFactory->create(SummaryJobType::EST, $request->query);

        $formViews['page'] = $viewModel;
        $formViews['accessionActiveInputTab'] = 0;
        $formViews['fastaActiveInputTab'] = 0;

        // Only create and handle forms if the user has permission for that
        if ($this->isGranted(SubmissionVoter::JOB_SUBMISSION)) {
            $transferRequestOptions = $transferService->getParentTransferRequestOptions($request);
            $formMap = $this->createAllForms($transferRequestOptions ?? [], $showDevFeatures);

            // Validate and set the active form
            if (isset($formMap[$targetForm]) && $transferRequestOptions) {
                $viewModel->activeForm = $targetForm;
                if ($targetForm === 'estAccessionForm') {
                    $formViews['accessionActiveInputTab'] = $transferRequestOptions['estAccessionForm']['parent_seq_type'] === SequenceType::UniProt ? 0 : 1;
                }
            }

            foreach ($formMap as $name => $data) {
                $formViews[$name] = $data->form;

                // Only handle the form that was actually submitted
                if ($targetForm && $targetForm !== $name) {
                    continue;
                }

                $handled = $this->submissionService->handleFormAndSetFlash($data);

                if ($handled) {
                    $this->submissionEmailDispatcher->queue($data->job);
                    // A job was successfully created, redirect immediately
                    return $this->redirectToRoute('est_home');

                } else if ($this->hasUnirefData($data->form)){
                    // If the form was submitted, there were errors, and is Accession, then we
                    // verify the sequence type and set the active tab accordingly
                    if ($targetForm === 'estAccessionForm') {
                        $formViews['accessionActiveInputTab'] = 1;
                    } else if ($targetForm === 'estFastaForm') {
                        $formViews['fastaActiveInputTab'] = 1;
                    }
                }
            }
        }

        return $this->render('efi-est/home.html.twig', $formViews);
    }

    /**
     * Check if any of the UniRef input tabs for Accession and FASTA are populated.
     * @param {FormInterface} $form - form to check
     * @return true if either the uniref text field or file field was used
     */
    private function hasUnirefData(FormInterface $form): bool
    {
        $fields = ['fileUniref', 'textUniref'];

        foreach ($fields as $fieldName) {
            if (!$form->has($fieldName)) {
                continue;
            }

            $field = $form->get($fieldName);
            $data = $field->getData();

            // For FileType, $data will be a Symfony\Component\HttpFoundation\File\UploadedFile,
            // and is not considered an empty string
            if ($data !== null && $data !== '') {
                return true;
            }
        }

        return false;
    }

    private function createAllForms(array $parentOptions, bool $showDevFeatures): array
    {
        $estAccessionFormData = null;
        // If there was a transfer request, then add the suggested name to the job form
        if (isset($parentOptions['estAccessionForm']['parent_sequences'])) {
            $estAccessionFormData = [];
            $estAccessionFormData['jobName'] = $parentOptions['estAccessionForm']['suggested_name'] ?? null;
            if ($parentOptions['estAccessionForm']['parent_seq_type'] !== SequenceType::UniProt) {
                $estAccessionFormData['unirefVersion'] = $parentOptions['estAccessionForm']['parent_seq_type'];
                $estAccessionFormData['textUniref'] = $parentOptions['estAccessionForm']['parent_sequences'];
            } else {
                $estAccessionFormData['textUniprot'] = $parentOptions['estAccessionForm']['parent_sequences'];
            }
        }

        $showActiveDatabases = ! (isset($parentOptions['estColorSsnForm'])
                                   || isset($parentOptions['estNbConnForm'])
                                   || isset($parentOptions['estConvRatioForm'])
                                   || isset($parentOptions['estCAForm']));

        $formMap = [];
        $formMap['estBlastForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'est_home',
                'estBlastForm',
                \App\Form\ESTGenerateBlastForm::class,
                \App\Entity\ESTGenerateBlastJob::class,
                'BLAST',
                [
                    'show_dev_features' => $showDevFeatures,
                ]
            ),
            JobFormSubmissionHandlerType::StringToFile
        );
        $formMap['estFamiliesForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'est_home',
                'estFamiliesForm',
                \App\Form\ESTGenerateFamiliesForm::class,
                \App\Entity\ESTGenerateFamiliesJob::class,
                'Families',
                [
                    'render_fraction_in_family_widget' => false,
                    'exclude_domain_family' => true,
                    'show_sequence_type_in_family_widget' => true,
                    'show_dev_features' => $showDevFeatures,
                ]
            ),
            JobFormSubmissionHandlerType::Basic
        );
        $formMap['estFastaForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'est_home',
                'estFastaForm',
                \App\Form\ESTGenerateFastaForm::class,
                \App\Entity\ESTGenerateFastaJob::class,
                'FASTA',
                [
                    'show_dev_features' => $showDevFeatures,
                ]
            ),
            JobFormSubmissionHandlerType::StringOrFile
        );
        $formMap['estAccessionForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'est_home',
                'estAccessionForm',
                \App\Form\ESTGenerateAccessionForm::class,
                \App\Entity\ESTGenerateAccessionJob::class,
                'Accession',
                [
                    'show_dev_features' => $showDevFeatures,
                ],
                $estAccessionFormData,
            ),
            JobFormSubmissionHandlerType::StringOrFile
        );
        $formMap['estColorSsnForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'est_home',
                'estColorSsnForm',
                \App\Form\ESTColorSSNForm::class,
                \App\Entity\ESTColorSSNJob::class,
                'Color SSN',
                array_merge(
                    [
                        'show_dev_features' => $showDevFeatures,
                        'show_active_databases' => $showActiveDatabases,
                    ],
                    $parentOptions['estColorSsnForm'] ?? [])
            ),
            JobFormSubmissionHandlerType::Uploaded
        );
        $formMap['estNbConnForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'est_home',
                'estNbConnForm', 
                \App\Form\ESTNeighborhoodConnectivityForm::class,
                \App\Entity\ESTNeighborhoodConnectivityJob::class,
                'Neighborhood Connectivity',
                array_merge(
                    [
                        'show_dev_features' => $showDevFeatures,
                        'show_active_databases' => $showActiveDatabases,
                    ],
                    $parentOptions['estNbConnForm'] ?? [])
            ),
            JobFormSubmissionHandlerType::Uploaded
        );
        $formMap['estConvRatioForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'est_home',
                'estConvRatioForm',
                \App\Form\ESTConvergenceRatioForm::class,
                \App\Entity\ESTConvergenceRatioJob::class,
                'Convergence Ratio',
                array_merge(
                    [
                        'show_dev_features' => $showDevFeatures,
                        'show_active_databases' => $showActiveDatabases,
                    ],
                    $parentOptions['estConvRatioForm'] ?? [])
            ),
            JobFormSubmissionHandlerType::Uploaded
        );
        $formMap['estCAForm'] = $this->jobFormFactory->createJobForm(
            new JobFormFactoryData(
                'est_home',
                'estCAForm',
                \App\Form\ESTClusterAnalysisForm::class,
                \App\Entity\ESTClusterAnalysisJob::class,
                'Cluster Analysis',
                array_merge(
                    [
                        'show_dev_features' => $showDevFeatures,
                        'show_active_databases' => $showActiveDatabases,
                    ],
                    $parentOptions['estCAForm'] ?? [])
            ),
            JobFormSubmissionHandlerType::Uploaded
        );
        return $formMap;
    }
}
