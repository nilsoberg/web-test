<?php

namespace App\Dto;

class CGFPMetagenomeDatabase
{
    private array $data = [];
    private array $bodySites = [];

    public function __construct(array $metadata, array $metagenomes, array $bodysiteFilter)
    {
        $this->populate($metadata, $metagenomes, $bodysiteFilter);

        $this->sortMetagenomes();
    }

    public function getBodySites(): array
    {
        return array_keys($this->bodySites);
    }

    private function sortMetagenomes(): void
    {
        $data = $this->data;
        $sort_fn = function($a, $b) use ($data) {
            $regionA = $data[$a] ?? null;
            $regionB = $data[$b] ?? null;

            if ($regionA === null || $regionB === null) {
                return 0;
            }

            $comp = $regionA->order <=> $regionB->order;
            if ($comp !== 0) {
                return $comp;
            }

            $comp = strcmp($regionA->site, $regionB->site);
            if ($comp !== 0) {
                return $comp;
            }

            return strcmp($a, $b);
        };
        uksort($this->data, $sort_fn);
    }

    private function add(string $metagenomeId, CGFPMetagenomeRegion $region): void
    {
        $this->data[$metagenomeId] = $region;
    }

    public function get(string $metagenomeId): ?CGFPMetagenomeRegion
    {
        return isset($this->data[$metagenomeId]) ? $this->data[$metagenomeId] : null;
    }

    private function populate(array $metadata, array $metagenomes, array $bodysiteFilter): void
    {
        $regionMap = [];
        if (is_array($metadata['regions'])) {
            for ($i = 0; $i < count($metadata['regions']); $i++) {
                $info = $metadata['regions'][$i];
                $regionName = $info['name'];
                if ($regionName !== null) {
                    $regionData = new CGFPMetagenomeRegion(
                        order: $i,
                        site: $regionName,
                        color: $info['color'] ?? '',
                    );
                    $regionMap[$regionName] = $regionData;
                }
            }
        }

        foreach ($metagenomes as $info) {
            $id = $info['sample_id'];
            if ($id === null) {
                continue;
            }

            $region = isset($info['body_site']) ? $regionMap[ $info['body_site'] ] ?? null : null;

            if ($region === null) {
                continue;
            }

            // If the region filter is populated, then ensure that we only include metagenomes that
            // are part of the specific region
            if ($region === null || count($bodysiteFilter) === 0 || in_array($region->site, $bodysiteFilter)) {
                $this->add($id, $region);
            }
        }

        $this->bodySites = $regionMap;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
