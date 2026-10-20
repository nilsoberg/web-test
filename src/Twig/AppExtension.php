<?php

namespace App\Twig;

use App\Entity\DisplayName;
use App\Service\LocalizedDateFormatterService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly LocalizedDateFormatterService $formatterService,
        private readonly string $accessMode,
    )
    {
    }

    public function getGlobals(): array
    {
        return [
            'is_app_public_mode' => $this->accessMode === \App\Auth\AccessMode::PUBLIC,
            'access_mode' => $this->accessMode,
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('display_name', [DisplayName::class, 'getFrom']),
        ];
    }

    public function getFilters(): array
    {
        return [
            // Usage: {{ date | date_skeleton('MMMd') }}
            new TwigFilter('date_skeleton', [$this, 'formatDateSkeleton']),
        ];
    }

    public function formatDateSkeleton(\DateTimeInterface|null $date, string $skeleton, string $locale = null): string
    {
        return $this->formatterService->format($date, skeleton: $skeleton);
    }
}
