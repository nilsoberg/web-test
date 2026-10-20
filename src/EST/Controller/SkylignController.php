<?php

namespace App\EST\Controller;

use App\Attribute\RateLimit;
use App\Service\ClusterAnalysisResultFileService;
use App\Service\JobRetrieverService;
use App\Service\ResultFileDownloadService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\File;

#[Route('/efi-est/results/skylign')]
class SkylignController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ResultFileDownloadService $downloadService,
    )
    {
    }

    #[Route('/upload-viewer/{key}', name: 'skylign_viewer_from_upload', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function skylign_viewer_from_upload(
        Uuid $key,
    ): Response
    {
        $uploadDir = $this->getParameter('app.gnd_uploads_directory');
        $file = $uploadDir . '/' . $key->toString() . '.json';

        if (!file_exists($file)) {
            throw new HttpException(500, 'Skylign Upload Viewer error: file does not exist');
        }

        $logoJson = file_get_contents($file);
        $viewData = ['logo_json' => $logoJson];

        return $this->render('efi-est/results/skylign_viewer.html.twig', $viewData);
    }

    // Show the Skylign viewer
    #[Route('/viewer/{id}/{key}/{clusterId}/{isDomain}', name: 'efi_est_ca_skylign_viewer', requirements: ['key' => Requirement::UUID_V6, 'clusterId' => '[cC]luster[_-]\d+(-\d+)*'], methods: ['GET'])]
    public function efi_est_ca_skylign_viewer(
        int $id,
        Uuid $key,
        string $clusterId,
        ClusterAnalysisResultFileService $analysisService,
        JobRetrieverService $jobRetrieverService,
        ?bool $isDomain = null,
    ): Response
    {
        $job = $jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        if (!$analysisService->validateCluster($job, $clusterId)) {
            throw new \InvalidArgumentException('Invalid cluster ID.');
        }

        if ($isDomain === null) {
            $isDomain = false;
        }

        $logoJson = $analysisService->getSkylignData($job, $clusterId, $isDomain);
        $viewData = ['logo_json' => $logoJson];

        return $this->render('efi-est/results/skylign_viewer.html.twig', $viewData);
    }

	#[Route('/upload', name: 'skylign_viewer_upload')]
    #[RateLimit(limiter: 'job_submission')]
    public function skylign_viewer_upload(
        Request $request,
    ): Response
    {
        $uploadDir = $this->getParameter('app.gnd_uploads_directory');

        if (empty($uploadDir)) {
            throw new HttpException(500, 'Server Configuration Error: "app.gnd_uploads_directory" is invalid or empty.');
        }

        // Create a standalone form without a data class
        $form = $this->createFormBuilder()
            ->add('attachment', FileType::class, [
                'label' => 'Upload Skylign JSON File',
                'constraints' => [
                    new File([
                        'extensions' => [
                            'txt',
                            'json',
                        ],
                        'uploadErrorMessage' => 'There was an error uploading your file. Please try again.',
                    ])
                ],
            ])
            ->add('upload', SubmitType::class, ['label' => 'Upload and Generate Link'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('attachment')->getData();

            if ($file) {
                $uuid = Uuid::v6()->toRfc4122();
                $newFilename = $uuid . '.json';

                try {
                    $file->move($uploadDir, $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Unable to upload file');
                    $this->logger->error('Could not move Skylign file', [
                        'message' => $e->getMessage(),
                        'file_path' => "$uploadDir/$newFilename",
                    ]);
                    return $this->redirectToRoute('skylign_viewer_upload');
                }

                return $this->redirectToRoute('skylign_viewer_from_upload', ['key' => $uuid]);
            }
        }

        return $this->render('efi-est/results/skylign_upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
