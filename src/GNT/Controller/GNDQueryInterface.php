<?php

namespace App\GNT\Controller;

use Efi\GndViewerBundle\Dto\QueryInterface;
use Symfony\Component\HttpFoundation\InputBag;

class GNDQueryInterface implements QueryInterface
{
    public function __construct(
        private readonly InputBag $requestQuery
    )
    {
    }

    public function get(string $key, mixed $default = null): string|int|float|bool|null
    {
        return $this->requestQuery->get($key, $default);
    }

    public function getInt(string $key): ?int
    {
        return $this->requestQuery->getInt($key);
    }

    public function has(string $key): bool
    {
        return $this->requestQuery->has($key);
    }
}
