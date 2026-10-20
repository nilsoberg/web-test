<?php

namespace App\Service;

use App\Entity\ESTSSNFinalizationJob;

class SSNResultStatsService
{
    public function __construct(
        private readonly FormatterService $formatter,
        private readonly ResultFileService $fileService,
    )
    {
    }

    public function loadStats(ESTSSNFinalizationJob $job): array
    {
        $statsJson = $job->getResultFileStats();
        if ($statsJson === null)
            return [];

        $resultStats = json_decode($statsJson, true);
        if ($resultStats === null)
            return [];

        $stats = [];

        foreach ($resultStats as $fileName => $fileStats) {
            if (str_contains($fileName, 'full_ssn')) {
                $type = 'full';
                $alias = 'full_ssn_network';
                $pct = 100;
            } else {
                $type = preg_replace('/^(repnode_[0-9]+)_.*$/', '$1', $fileName);
                $alias = "{$type}_network";
                $pct = preg_replace('/^repnode_([0-9]+)_.*$/', '$1', $fileName);
            }

            // Get the actual size of zipped file; not the xgmml size provided by the stats
            $filePath = $this->fileService->getResultFilePath($job, $alias);
            $size = filesize($filePath);

            $stats[$alias] = [
                'nodes' => $fileStats['num_nodes'] ?? 0,
                'edges' => $fileStats['num_edges'] ?? 0,
                'size' => $size,
                'percentage' => $pct,
                'name' => $fileName,
                'type' => $type,
            ];
        }

        uasort($stats, function($a, $b) { return (int)$b['percentage'] <=> (int)$a['percentage']; });

        return $stats;
    }
}
