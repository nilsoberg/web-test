<?php

namespace App\ViewModel;

final readonly class CGFPQuantifyAbundanceViewModel
{
    public function __construct(
        public ?NetworkFileViewModel $proteinMedian,
        public ?NetworkFileViewModel $clusterMedian,
        public ?NetworkFileViewModel $proteinNormMedian,
        public ?NetworkFileViewModel $clusterNormMedian,
        public ?NetworkFileViewModel $proteinMean,
        public ?NetworkFileViewModel $clusterMean,
        public ?NetworkFileViewModel $proteinNormMean,
        public ?NetworkFileViewModel $clusterNormMean,
    )
    {
    }
}
