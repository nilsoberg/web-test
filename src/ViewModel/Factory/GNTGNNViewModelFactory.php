<?php

namespace App\ViewModel\Factory;

use App\Entity\GNTGNNJob;
use App\Entity\Job;
use App\GNT\Controller\GNDController;
use App\GNT\Controller\GNTController;
use App\Service\FormatterService;
use App\Service\JobSummaryService;
use App\Service\ResultFileService;
use App\ViewModel\NetworkFileViewModel;
use App\ViewModel\GNTGNNViewModel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GNTGNNViewModelFactory
{
    // Inject the project's data directory path via services.yaml
    public function __construct(
        private readonly FormatterService $formatter,
        private readonly JobSummaryService $summaryService,
        private readonly ResultFileService $fileService,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
	{
    }

    /**
     * The main public method to create the list of view models.
     *
     * @return GNTGNNViewModel
     */
    public function create(GNTGNNJob $job): GNTGNNViewModel
    {
        $summaryData = $this->summaryService->getDisplaySummaryForJob($job);

        $resultDir = $this->fileService->getResultsDirectory($job);

        $colorSsn = $this->getColorSsn($job, $resultDir);
        $ssnGnn = $this->getGnn($job, 'gnn_ssn_cluster_gnn');
        $pfamGnn = $this->getGnn($job, 'gnn_pfam_family_gnn');

        $otherFiles = $this->getOtherFiles($job, $resultDir);

        $gndDownloadSize = $this->getGndDownloadSize($resultDir);
        $gndDownloadRoute = $this->generateUrl($job, 'gnn_gnd_download');

        $gndViewerRoute = $this->urlGenerator->generate(GNDController::GND_VIEWER_ROUTE_NAME, [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
        ]);

        $summaryDownloadUrl = $this->urlGenerator->generate(GNTController::DATASET_DOWNLOAD_ROUTE_NAME, [
            'id' => $job->getId(),
            'key' => $job->getUuid()
        ]);

        $viewModel = new GNTGNNViewModel(
            jobName: $job->getJobName() ?? '',
            summaryData: $summaryData,
            summaryDownloadUrl: $summaryDownloadUrl,
            colorSsn: $colorSsn,
            ssnGnn: $ssnGnn,
            pfamGnn: $pfamGnn,
            otherFiles: $otherFiles,
            gndDownloadRoute: $gndDownloadRoute,
            gndDownloadSize: $gndDownloadSize,
            gndViewerRoute: $gndViewerRoute
        );

        return $viewModel;
    }

    private function getGndDownloadSize(string $resultDir): string
    {
        $gndFile = $resultDir . '/gnd.zip';

        if (file_exists($gndFile)) {
            $size = filesize($gndFile);
            $zipSize = $this->formatter->formatSizeUnitsMb($size);
        } else {
            $files = glob($resultDir . '/*.sqlite');
            if (count($files) == 0) {
                return 'N/A';
            }

            $gndPath = $files[0];
            $info = pathinfo($gndPath);

            $zipPath = $info['dirname'] . '/' . $info['filename'] . '.zip';

            if (file_exists($zipPath)) {
                $size = filesize($zipPath);
                $zipSize = $this->formatter->formatSizeUnitsMb($size);
            } else {
                $zipSize = 'N/A';
            }
        }

        return $zipSize;
    }

    private function getOtherFiles(GNTGNNJob $job, string $resultDir): array
    {
        $pfamFiles = [
            'all_pfam_mapping.zip' => 'Neighbor Pfam domain fusions at 0% minimal co-occurrence frequency',
            'all_split_pfam_mapping.zip' => 'Neighbor Pfam domains at 0% minimal co-occurrence frequency',
            'pfam_mapping.zip' => 'Neighbor Pfam domain fusions at specified minimal co-occurrence frequency',
            'split_pfam_mapping.zip' => 'Neighbor Pfam domains at specified minimal co-occurrence frequency',
            'no_pfam_neighbors.zip' => 'Neighbors without Pfam assigned',
        ];
        $mappingTables = $this->processOtherFileGroup($job, $pfamFiles, $resultDir);

        $miscFiles = [
            'cluster_sizes.txt' => 'Cluster size file',
            'cooc_table.txt' => 'Pfam family/cluster co-occurrence table file',
            'hub_count.txt' => 'GNN hub cluster sequence count file',
            'nomatches_noneighbors.txt' => 'No matches/no neighbors file',
            'swissprot_clusters_desc.txt' => 'SwissProt annotations per SSN cluster',
            'swissprot_singletons_desc.txt' => 'SwissProt annotations by singleton',
        ];
        $miscDownloads = $this->processOtherFileGroup($job, $miscFiles, $resultDir);

        return array('mappingTables' => $mappingTables, 'miscDownloads' => $miscDownloads);
    }

    private function processOtherFileGroup(GNTGNNJob $job, array $files, string $resultDir): array
    {
        $downloads = [];
        foreach ($files as $file => $name) {
            $filePath = $resultDir . '/' . $file;
            if (!file_exists($filePath)) {
                continue;
            }
            $size = $this->formatter->formatSizeUnitsMb(filesize($filePath));
            $filename = pathinfo($file)['filename'];
            $routeName = 'gnn_' . $filename;
            $route = $this->generateUrl($job, $routeName);
            $downloads[] = new NetworkFileViewModel(
                name: $name,
                size: $size,
                route: $route
            );
        }

        return $downloads;
    }

    private function getGnn(GNTGNNJob $job, string $fileTypeAlias): NetworkFileViewModel
    {
        $filePath = $this->fileService->getResultFilePath($job, $fileTypeAlias);

        $size = filesize($filePath);
        $gnnSize = $this->formatter->formatSizeUnitsMb($size);

        $downloadRoute = $this->generateUrl($job, $fileTypeAlias);

        $ssnGnn = new NetworkFileViewModel(
            name: $fileTypeAlias, // Required in the view module but not used in the template
            size: $gnnSize,
            route: $downloadRoute
        );

        return $ssnGnn;
    }

    private function getColorSsn(GNTGNNJob $job, string $resultDir): ?NetworkFileViewModel
    {
        $filePath = $this->fileService->getResultFilePath($job, 'gnn_color_ssn');

        $size = filesize($filePath);
        $ssnSize = $this->formatter->formatSizeUnitsMbGb($size);

        $downloadRoute = $this->generateUrl($job, 'gnn_color_ssn');

        $resultFileStats = $job->getResultFileStats();

        if ($resultFileStats !== null) {
            $stats = $this->loadStats($resultFileStats);
        } else {
            $stats = [ 'color_ssn' => [ 'num_nodes' => null, 'num_edges' => null ] ];
        }

        $ssnFile = new NetworkFileViewModel(
            name: 'coloredssn',
            nodes: $stats['color_ssn']['num_nodes'],
            edges: $stats['color_ssn']['num_edges'],
            size: $ssnSize,
            route: $downloadRoute
        );

        return $ssnFile;
    }

    private function generateUrl(Job $job, string $fileType): string
    {
        $url = $this->urlGenerator->generate(GNTController::DOWNLOAD_ROUTE_NAME, [
            'id' => $job->getId(),
            'key' => $job->getUuid(),
            'fileType' => $fileType,
        ]);
        return $url;
    }

    private function loadStats(?string $statsJson): array
    {
        if ($statsJson === null)
            return [];

        $allStats = json_decode($statsJson, true);
        if ($allStats === null)
            return [];

        return [
            'color_ssn' => [
                'num_nodes' => $allStats['color_ssn']['num_nodes'] ?? null,
                'num_edges' => $allStats['color_ssn']['num_edges'] ?? null,
            ],
        ];
    }
}
