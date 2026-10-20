<?php

namespace App\ViewModel;

/**
 * A simple ViewModel to hold the display data for a single file.
 * This makes the data structure passed to the template explicit.
 */
final readonly class NetworkFileViewModel
{
    public function __construct(
        public string $name,
        public string $size,
        public string $route,

        public ?int $index = null,
        public ?string $percentId = null,
        public ?string $nodes = null,
        public ?string $edges = null,
        public ?string $path = null,
        public ?string $alias = null,

        public ?string $transferId = null,

        public ?string $ncScaleRoute = null,
    )
    {
    }
}
