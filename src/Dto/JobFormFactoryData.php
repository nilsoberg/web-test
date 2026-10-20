<?php

namespace App\Dto;

class JobFormFactoryData
{
    public function __construct(
        public readonly ?string $route,
        public readonly string $formName,
        public readonly string $formClass,
        public readonly string $entityClass,
        public readonly string $title,
        public readonly array $extraOptions = [],
        public readonly ?array $initialFormData = null,
    )
    {
    }
}
