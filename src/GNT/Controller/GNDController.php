<?php

namespace App\GNT\Controller;

use App\Service\JobRetrieverService;
use App\Service\ResultFileDownloadService;
use App\Service\ResultFileService;
use Efi\GndViewerBundle\Dto\GndRequestParams;
use Efi\GndViewerBundle\Service\GndViewerInterface;
use Efi\GndViewerBundle\Service\GndViewerSQLiteFactory;
use Efi\GndViewerBundle\Service\GeneGraphicsExporter;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\File;

#[Route('/efi-gnt/gnd')]
class GNDController extends AbstractController
{
    const GND_FILE_TYPE_ALIAS = 'gnn_gnd_database';

    public const GND_VIEWER_ROUTE_NAME = 'gnd_viewer';

    public function __construct(
        private readonly GndViewerSQLiteFactory $gndFactoryService,
        private readonly JobRetrieverService $jobService,
        private readonly LoggerInterface $logger,
        private readonly Packages $assetMapper,
        private readonly ResultFileService $fileService,
        private readonly ResultFileDownloadService $downloadService,
    )
    {
    }




    #[Route('/viewer/{id}/{key}', name: self::GND_VIEWER_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function gnd_viewer(
        int $id,
        Uuid $key,
        Request $request,
    ): Response
    {
        $gndService = $this->getJobGndService($id, $key);
        $params = $this->getGndViewerParams($id, $key, $gndService, $request, true);
        $viewData = ['params' => $params];
        return $this->render('efi-gnt/gnd/viewer.html.twig', $viewData);
    }

    #[Route('/viewer-upload/{key}', name: 'gnd_viewer_from_upload', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function gnd_viewer_from_upload(
        Uuid $key,
        Request $request,
    ): Response
    {
        $id = 0; // Not needed in this mode
        $gndService = $this->getUploadGndService($key);
        $params = $this->getGndViewerParams($id, $key, $gndService, $request, false);
        $viewData = ['params' => $params];
        return $this->render('efi-gnt/gnd/viewer.html.twig', $viewData);
    }




    #[Route('/api/export_gene_graphics', name: 'export_gene_graphics', requirements: ['key' => Requirement::UUID_V6], methods: ['POST'])]
    public function export_gene_graphics(
        #[MapQueryParameter] int $id,
        #[MapQueryParameter] string $key,
        #[MapRequestPayload] GndRequestParams $params,
    ): Response
    {
        $gndService = $this->getJobGndService($id, $key);
        return $this->exportGeneGraphicsResponse($gndService, $params);
    }

    #[Route('/api/export_gene_graphics_upload', name: 'export_gene_graphics_upload', requirements: ['key' => Requirement::UUID_V6], methods: ['POST'])]
    public function export_gene_graphics_upload(
        #[MapQueryParameter] int $id,
        #[MapQueryParameter] string $key,
        #[MapRequestPayload] GndRequestParams $params,
    ): Response
    {
        $gndService = $this->getUploadGndService($key);
        return $this->exportGeneGraphicsResponse($gndService, $params);
    }




    #[Route('/download/{id}/{key}', name: 'download_db', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function download_db(
        int $id,
        Uuid $key,
    ): Response
    {
        $dbFile = $this->getJobGndDatabaseDsn($id, $key);
        return $this->downloadDbResponse($dbFile);
    }

    #[Route('/download-upload/{id}/{key}', name: 'download_db_upload', requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function download_db_upload(
        int $id,
        Uuid $key,
    ): Response
    {
        $dbFile = $this->getUploadGndDatabaseDsn($key);
        return $this->downloadDbResponse($dbFile);
    }




    #[Route('/api/search', name: 'api_gnd_search', methods: ['GET'])]
    public function api_gnd_search(
        #[MapQueryParameter] int $id,
        #[MapQueryParameter] string $key,
        Request $request,
    ): JsonResponse
    {
        $gndService = $this->getJobGndService($id, $key);
        return $this->searchApiResponse($gndService, $request);
    }

    #[Route('/api/search-upload', name: 'api_gnd_search_upload', methods: ['GET'])]
    public function api_gnd_search_upload(
        #[MapQueryParameter] int $id,
        #[MapQueryParameter] string $key,
        Request $request,
    ): JsonResponse
    {
        $gndService = $this->getUploadGndService($key);
        return $this->searchApiResponse($gndService, $request);
    }

    private function searchApiResponse(GndViewerInterface $gndService, Request $request): JsonResponse
    {
        $query = new GNDQueryInterface($request->query);
        $results = $gndService->processGndSearch($query);
        return new JsonResponse($results);
    }




    #[Route('/api/record', name: 'api_gnd_record', methods: ['GET'])]
    public function api_gnd_record(
        #[MapQueryParameter] int $id,
        #[MapQueryParameter] string $key,
        #[MapQueryParameter] string $range,
        Request $request,
    ): JsonResponse
    {
        $gndService = $this->getJobGndService($id, $key);
        return $this->recordApiResponse($gndService, $range, $request);
    }

    #[Route('/api/record-upload', name: 'api_gnd_record_upload', methods: ['GET'])]
    public function api_gnd_record_upload(
        #[MapQueryParameter] int $id,
        #[MapQueryParameter] string $key,
        #[MapQueryParameter] string $range,
        Request $request,
    ): JsonResponse
    {
        $gndService = $this->getUploadGndService($key);
        return $this->recordApiResponse($gndService, $range, $request);
    }




	#[Route('/upload', name: 'gnd_viewer_upload')]
    public function gnd_viewer_upload(Request $request): Response
    {
        $uploadDir = $this->getParameter('app.gnd_uploads_directory');

        if (empty($uploadDir)) {
            throw new HttpException(500, 'Server Configuration Error: "app.gnd_uploads_directory" is invalid or empty.');
        }

        // Create a standalone form without a data class
        $form = $this->createFormBuilder()
            ->add('attachment', FileType::class, [
                'label' => 'Upload GND File',
                'constraints' => [
                    new File([
                        'extensions' => [
                            'sqlite',
                            'sqlite3',
                            'zip',
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
                // Generate UUID
                $uuid = Uuid::v6()->toRfc4122();

                // Create filename: uuid.extension
                $newFilename = $uuid . '.sqlite';

                try {
                    $file->move($uploadDir, $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Unable to upload file');
                    $this->logger->error('Could not move GND file', [
                        'message' => $e->getMessage(),
                        'file_path' => "$uploadDir/$newFilename",
                    ]);
                    return $this->redirectToRoute('gnd_viewer_upload');
                }

                // Redirect to the specific viewer route requested
                return $this->redirectToRoute('gnd_viewer_from_upload', ['key' => $uuid]);
            }
        }

        return $this->render('efi-gnt/gnd/upload_gnd.html.twig', [
            'form' => $form->createView(),
        ]);
    }






    private function getGndViewerParams(int $id, Uuid $key, GndViewerInterface $gndService, Request $request, bool $isFromJob): array
    {
        $suffix = $isFromJob ? "" : "_upload";

        $query = new GNDQueryInterface($request->query);

        $metadataUrl = $this->getParameter('app.sequence_metadata_url') ?? '';
        $batchSize = $this->getParameter('app.gnd_viewer_batch_size') ?? 10;
        $setSize = $this->getParameter('app.gnd_viewer_set_size') ?? 20;

        $downloadDbUrl = $this->generateUrl("download_db{$suffix}", [
            'id' => $id,
            'key' => $key,
        ]);
        $geneGraphicsUrl = $this->generateUrl("export_gene_graphics{$suffix}", [
            'id' => $id,
            'key' => $key,
        ]);
        $tutorialUrl = $this->assetMapper->getUrl($this->getParameter('app.gnd_diagram_tutorial_asset'));

        $params = $gndService->getViewerParameters($gndService, $query, $id, $key, [
            'metadata_url' => $metadataUrl,
            'batch_size' => $batchSize,
            'set_size' => $setSize,
            'api_gnd_search_url' => $this->generateUrl("api_gnd_search{$suffix}"),
            'api_gnd_record_url' => $this->generateUrl("api_gnd_record{$suffix}"),
            'gnt_home_url' => $this->generateUrl('gnt_home'),
            'download_db_url' => $downloadDbUrl,
            'tutorial_url' => $tutorialUrl,
            'gene_graphics_url' => $geneGraphicsUrl,
        ]);

        return $params;
    }

    private function exportGeneGraphicsResponse(GndViewerInterface $gndService, GndRequestParams $params): Response
    {
        $metadata = $gndService->getMetadata();
        $filename = $this->fileService->getCleanFilename($metadata->jobName);

        $exporter = new GeneGraphicsExporter($gndService, $params);

        return $this->downloadService->createStreamedDownloadResponse($exporter, $filename, 'gnn_gnd_gene_graphics');
    }

    private function recordApiResponse(GndViewerInterface $gndService, string $range, Request $request): JsonResponse
    {
        $query = new GNDQueryInterface($request->query);
        $results = $gndService->retrieveGndData($query, $range);
        return new JsonResponse($results);
    }

    private function downloadDbResponse(string $dbFile): Response
    {
        $gndService = $this->gndFactoryService->createViewerForFile($dbFile);
        $metadata = $gndService->getMetadata();
        $filename = $this->fileService->getCleanFilename($metadata->jobName);
        return $this->downloadService->createDownloadResponseFromPathAndName($dbFile, $filename, 'gnn_gnd_database');
    }




    private function getJobGndService(int $id, string $key): GndViewerInterface
    {
        $dbFile = $this->getJobGndDatabaseDsn($id, $key);
        $gndService = $this->gndFactoryService->createViewerForFile($dbFile);
        return $gndService;
    }

    private function getUploadGndService(string $key): GndViewerInterface
    {
        $dbFile = $this->getUploadGndDatabaseDsn($key);
        $gndService = $this->gndFactoryService->createViewerForFile($dbFile);
        return $gndService;
    }

    /**
     * Return the DSN to a GND file from a Job.
     * @param {Job} $job - job object
     * @return {string} path to GND SQLite file
     */
    private function getJobGndDatabaseDsn(int $id, string $key): string
    {
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $dbFile = $this->fileService->getResultFilePath($job, self::GND_FILE_TYPE_ALIAS);

        if (!file_exists($dbFile)) {
            throw new HttpException(500, $dbFile);
        }

        return $dbFile;
    }

    private function getUploadGndDatabaseDsn(string $key): string
    {
        $gndUploadDir = $this->getParameter('app.gnd_uploads_directory');

        $dbFile = "$gndUploadDir/$key.sqlite";

        if (!file_exists($dbFile)) {
            throw new HttpException(500, $dbFile);
        }

        return $dbFile;
    }
}
