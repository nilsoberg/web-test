<?php

namespace App\Dto;

use App\Entity\DatabaseMetadata;

class DatabaseMetadataListItem
{
    public function __construct(
        public readonly DatabaseMetadata $database,
        public readonly bool $isOrgDefault,
    )
    {
    }
}
