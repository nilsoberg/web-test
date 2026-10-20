<?php

namespace App\CGFP\Controller;

use App\Dto\CGFPHeatmapAbundance;
use App\Entity\CGFPQuantifyJob;
use App\Enum\CGFPHeatmapType;
use App\Service\CGFPHeatmapDataService;
use App\Service\JobRetrieverService;
use App\Service\ResultFileService;
use App\ViewModel\Factory\CGFPResultsViewModelFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/efi-cgfp')]
class HeatmapController extends AbstractController
{
    public const HEATMAP_ROUTE_NAME = 'cgfp_quantify_heatmap';
    public const HEATMAP_DATA_API = 'api_get_cgfp_heatmap_data';
    public const BOXPLOT_ROUTE_NAME = 'cgfp_quantify_boxplot';

    public function __construct(
        private readonly CGFPHeatmapDataService $heatmapService,
        private readonly JobRetrieverService $jobService,
        private readonly ResultFileService $fileService,
    )
    {
    }

    #[Route('/quantify/{id}/{key}/heatmap/{type}', name: self::HEATMAP_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function heatmap(
        int $id,
        Uuid $key,
        CGFPHeatmapType $type,
        CGFPResultsViewModelFactory $factory,
    ): Response
    {
        // Find the job
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $identifyJob = $job->getParentJob();
        $identifyId = $identifyJob?->getId();

        $dataQueryRoute = $this->generateUrl(self::HEATMAP_DATA_API, ['id' => $id, 'key' => $key, 'type' => $type->value]);

        $viewModel = $factory->createHeatmap($job, $identifyJob, $type);

        return $this->render('efi-cgfp/quantify/heatmap_app.html.twig', [
            'job' => $job,
            'key' => $key,
            'quantify_id' => $id,
            'identify_id' => $identifyId,
            'data_query_route' => $dataQueryRoute,
            'config' => $viewModel,
        ]);
    }

    #[Route('/quantify/{id}/{key}/boxplot/{type}', name: self::BOXPLOT_ROUTE_NAME, requirements: ['key' => Requirement::UUID_V6], methods: ['GET'])]
    public function boxplot(
        int $id,
        Uuid $key,
        CGFPHeatmapType $type,
        CGFPResultsViewModelFactory $factory,
    ): Response
    {
        // Find the job
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $identifyJob = $job->getParentJob();
        $identifyId = $identifyJob?->getId();

        $dataQueryRoute = $this->generateUrl(self::HEATMAP_DATA_API, ['id' => $id, 'key' => $key, 'type' => $type->value]);

        $viewModel = $factory->createBoxplot($job, $identifyJob, $type);

        return $this->render('efi-cgfp/quantify/boxplot_app.html.twig', [
            'job' => $job,
            'key' => $key,
            'quantify_id' => $id,
            'identify_id' => $identifyId,
            'data_query_route' => $dataQueryRoute,
            'config' => $viewModel,
        ]);
    }

    #[Route('/quantify/{id}/{key}/heatmap-data/{type}', name: self::HEATMAP_DATA_API, requirements: ['key' => Requirement::UUID_V6], methods: ['POST'])]
    public function get_heatmap_data(
        Request $request,
        CGFPHeatmapType $type,
        int $id,
        Uuid $key,
    ): JsonResponse
    {
        // Find the job
        $job = $this->jobService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        $data = $this->getAbundanceData($job, $request->request, $type);

        $errorResult = ["valid" => false];

        if ($data === null) {
            return new JsonResponse($errorResult);
        }

        $result = [
            'clusters' => $data->clusterData,
            'metagenomes' => $data->metagenomes,
            'site_info' => $data->siteInfo,
            'extent' => ['min' => $data->minExtent, 'max' => $data->maxExtent],
            'available_body_sites' => $data->bodySites,
            'valid' => true,
        ];

        return new JsonResponse($result);
    }

    private function getAbundanceData(CGFPQuantifyJob $job, InputBag $request, CGFPHeatmapType $heatmapType): ?CGFPHeatmapAbundance
    {
        $fileType = $request->has('use-mean') ? 'quantify_cluster_abundance_genome_normalized_mean' : 'quantify_cluster_abundance_genome_normalized_median';
        $hitsOnly = $request->has('hits-only');

        $lowerThreshold = ($request->has("lower_thresh") && is_numeric($request->get("lower_thresh")))
            ? (float)$request->get("lower_thresh")
            : 0;

        $upperThreshold = ($request->has("upper-thresh") && is_numeric($request->get("upper-thresh")))
            ? (float)$request->get("upper-thresh")
            : 1000000;

        $clusterFilter = $request->has("clusters")
            ? $this->parseClusters($request->get("clusters"))
            : [];

        $file = $this->fileService->getResultFilePath($job, $fileType);

        $database = $job->getMetagenomeDatabase();

        $regions = [];
        if ($request->has("bodysites")) {
            $regions = explode("|", $request->get("bodysites"));
        }

        $data = $this->heatmapService->readAbundanceFile($file, $hitsOnly, $lowerThreshold, $upperThreshold, $clusterFilter, $regions, $heatmapType, $database);

        return $data;
    }

    private function parseClusters(string $params): array
    {
        $parts = explode(",", preg_replace("/[, ]+/", ",", $params));
        $nums = [];
        foreach ($parts as $part) {
            $part = strtoupper($part);
            if (is_numeric($part)) {
                $nums[$part] = true;
            } elseif (strpos($part, "S") !== false) {
                $rangeParts = explode("-", $part);
                if (count($rangeParts) == 2) {
                    $start = substr($rangeParts[0], 1);
                    $end = substr($rangeParts[1], 1);
                    $theRange = range($start, $end);
                    foreach ($theRange as $range_val) {
                        $nums["S$range_val"] = true;
                    }
                } else {
                    $nums[$part] = true;
                }
            } else {
                $rangeParts = explode("-", $part);
                if (count($rangeParts) == 2) {
                    $theRange = range($rangeParts[0], $rangeParts[1]);
                    foreach ($theRange as $range_val) {
                        $nums[$range_val] = true;
                    }
                }
            }
        }
        return $nums;
    }
}
