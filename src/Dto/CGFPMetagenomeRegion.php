<?php

namespace App\Dto;

class CGFPMetagenomeRegion {
    public function __construct(
        public readonly int $order,
        public readonly string $site,
        public readonly ?string $color,
    )
    {
    }
}
