<?php

namespace App\Service;

use App\Dto\CGFPHeatmapAbundance;
use App\Dto\CGFPMetagenomeDatabase;
use App\Dto\CGFPMetagenomeRegion;
use App\Entity\MetagenomeMetadata;
use App\Enum\CGFPHeatmapType;
use Psr\Log\LoggerInterface;

class CGFPHeatmapDataService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function readAbundanceFile(
        string $filePath,
        bool $hitsOnly,
        float $lowerThreshold,
        float $upperThreshold,
        array $clusterFilter,
        array $bodysiteFilter,
        CGFPHeatmapType $resultType,
        MetagenomeMetadata $database,
    ): ?CGFPHeatmapAbundance
    {
        try {
            $fh = fopen($filePath, 'r');
        } catch (\Exception $e) {
            $this->logger->error('Unable to read abundance file', [
                'path' => $filePath,
                'error' => $e,
            ]);
            return null;
        }

        $headerLine = trim(fgets($fh));
        $headers = explode("\t", $headerLine);

        $hasClusterSize = in_array('Cluster Size', $headers);
        $startIndex = $hasClusterSize ? 2 : 1;


        // Parse the header for metagenome IDs and for column mapping and ordering
        $headerIds = array_slice($headers, $startIndex);

        $metagenomes = [];
        $fileColumnMapping = [];

        $siteInfo = $this->getSiteInfo($database, $bodysiteFilter);

        for ($i = 0; $i < count($headerIds); $i++) {
            $metagenomeId = $headerIds[$i];
            if ($siteInfo->get($metagenomeId)?->order !== null) {
                $metagenomes[] = $metagenomeId;
            }
            $fileColumnMapping[$metagenomeId] = $i;
        }

        $metagenomeOrdering = [];
        for ($i = 0; $i < count($metagenomes); $i++) {
            $metagenomeOrdering[$metagenomes[$i]] = $i;
        }




        $clusters = [];
        $singletons = [];

        $min = 1000000;
        $max = -1000000;

        while (($line = fgets($fh)) !== false) {
            $parts = explode("\t", trim($line));

            $cluster = $parts[0];
            if ($cluster === '#N/A') {
                continue;
            }

            $size = substr($cluster, 0, 1) === 'S'
                ? 1
                : ($hasClusterSize ? $parts[1] : 2);

            if (substr($cluster, 0, 1) !== 'S' && $size === 1) {
                $cluster = "S$cluster";
            }

            $abundance = array_fill(0, count($metagenomes), 0);

            $sum = 0;

            for ($i = 0; $i < count($metagenomes); $i++) {
                $metagenomeId = $metagenomes[$i];
                $val = $parts[ $startIndex + $fileColumnMapping[$metagenomeId] ];

                $max = max($val, $max);
                $min = min($val, $min);

                if ($hitsOnly && $val > 0) {
                    $val = 1;
                } elseif ($val < $lowerThreshold) {
                    $val = 0;
                } elseif ($val > $upperThreshold) {
                    $val = 0;
                }

                $sum += $val;
                $abundance[ $metagenomeOrdering[$metagenomeId] ] = $val;
            }

            // Skip to the next line if this cluster is not in the filter list
            if (count($clusterFilter) && !isset($clusterFilter[$cluster])) {
                continue;
            }

            if ($resultType === CGFPHeatmapType::Singleton && $size > 1 && !isset($clusterFilter[$cluster])) {
                continue;
            }
            if ($resultType === CGFPHeatmapType::Cluster && $size < 2 && !isset($clusterFilter[$cluster])) {
                continue;
            }

            $info = [
                'number' => $cluster,
                'abundance' => $abundance,
            ];

            if ($sum >= 1e-6) {
                if ($size === 1) {
                    array_push($singletons, $info);
                } else {
                    array_push($clusters, $info);
                }
            }
        }

        fclose($fh);

        usort($singletons, [$this, 'cmpSingles']);

        $clusterData = array_merge($clusters, $singletons);

        $rawSiteInfo = $siteInfo->toArray();

        $bodySites = $siteInfo->getBodySites();

        return new CGFPHeatmapAbundance(
            clusterData: $clusterData,
            metagenomes: $metagenomes,
            siteInfo: $rawSiteInfo,
            bodySites: $bodySites,
            minExtent: $min,
            maxExtent: $max,
        );
    }

    private function getSiteInfo(MetagenomeMetadata $database, array $bodysiteFilter): CGFPMetagenomeDatabase
    {
        $md = $database->getMetadata();
        $metagenomes = $database->getMetagenomes();

        $siteInfo = new CGFPMetagenomeDatabase($md, $metagenomes, $bodysiteFilter);

        return $siteInfo;
    }

    private function cmpSingles(array $a, array $b): int
    {
        $aa = $a["number"];
        $bb = $b["number"];
        if (substr($aa, 0, 1) == "S") $aa = substr($aa, 1);
        if (substr($bb, 0, 1) == "S") $bb = substr($bb, 1);
        return $aa - $bb;
    }
}
