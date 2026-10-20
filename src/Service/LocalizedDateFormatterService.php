<?php

namespace App\Service;

use IntlDateFormatter;
use IntlDatePatternGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LocalizedDateFormatterService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        #[Autowire('%app.timezone%')]
        private readonly string $defaultTimezone
    )
    {
    }

    /**
     * @param \DateTimeInterface|null $date
     * @param string|null $skeleton  e.g. 'MMMd', 'jm'
     * @param string $dateStyle      'short', 'medium', 'long', 'full', 'none'
     * @param string $timeStyle      'short', 'medium', 'long', 'full', 'none'
     * @param string|null $pattern   Force a specific pattern (overrides everything)
     * @param string|null $locale    Force a specific locale
     * @param string|null $timezone  Force a specific timezone
     */
    public function format(
        ?\DateTimeInterface $date,
        ?string $skeleton = null,
        string $dateStyle = 'medium',
        string $timeStyle = 'short',
        ?string $pattern = null,
        ?string $locale = null,
        ?string $timezone = null
    ): string
    {
        if (null === $date) {
            return '';
        }

        // Resolve Locale (Current Request -> Default 'en')
        $locale = $locale ?? $this->resolveLocale();

    	// Resolve Timezone (User Preference -> App Default)
        $timezone = $timezone ?? $this->resolveTimezone();

        // XAMPP builds may not include ext-intl; use a PHP-native formatter fallback.
        if (!class_exists(IntlDateFormatter::class)) {
            return $this->formatFallback($date, $skeleton, $dateStyle, $timeStyle, $timezone);
        }

        // Resolve Date/time Pattern
        // Priority: Pattern > Skeleton > Styles
        $finalPattern = $pattern;

        if (!$finalPattern && $skeleton) {
            if (class_exists(IntlDatePatternGenerator::class)) {
                $generator = new IntlDatePatternGenerator($locale);
                $finalPattern = $generator->getBestPattern($skeleton);
            } else {
                return $this->formatFallback($date, $skeleton, $dateStyle, $timeStyle, $timezone);
            }
        }

        // Resolve Styles (if no pattern/skeleton)
        $dateType = $finalPattern ? IntlDateFormatter::NONE : $this->getIntlStyle($dateStyle);
        $timeType = $finalPattern ? IntlDateFormatter::NONE : $this->getIntlStyle($timeStyle);

        // Format
        $formatter = new IntlDateFormatter(
            $locale,
            $dateType,
            $timeType,
            $timezone,
            null,
            $finalPattern
        );

        $formatted = $formatter->format($date);
        if ($formatted === false) {
            return $this->formatFallback($date, $skeleton, $dateStyle, $timeStyle, $timezone);
        }

        return $formatted;
    }

    private function resolveLocale(): string
    {
        // Try the current request, fall back to system default
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? \Locale::getDefault();
    }

    private function resolveTimezone(): string
    {
        $user = $this->security->getUser();
        if ($user && method_exists($user, 'getTimezone') && $user->getTimezone()) {
            return $user->getTimezone();
        }
        return $this->defaultTimezone;
    }

    private function getIntlStyle(string $style): int
    {
        return match ($style) {
            'none' => IntlDateFormatter::NONE,
            'short' => IntlDateFormatter::SHORT,
            'long' => IntlDateFormatter::LONG,
            'full' => IntlDateFormatter::FULL,
            default => IntlDateFormatter::MEDIUM,
        };
    }

    private function formatFallback(
        \DateTimeInterface $date,
        ?string $skeleton,
        string $dateStyle,
        string $timeStyle,
        string $timezone
    ): string {
        try {
            $dateForFormat = \DateTimeImmutable::createFromInterface($date)
                ->setTimezone(new \DateTimeZone($timezone));
        } catch (\Throwable) {
            $dateForFormat = \DateTimeImmutable::createFromInterface($date);
        }

        $skeletonFormatMap = [
            'MMMd' => 'M j',
            'MMMdjm' => 'M j, g:i A',
            'yMMMdjm' => 'Y M j, g:i A',
        ];

        if ($skeleton && isset($skeletonFormatMap[$skeleton])) {
            return $dateForFormat->format($skeletonFormatMap[$skeleton]);
        }

        $dateFormatMap = [
            'none' => '',
            'short' => 'n/j/y',
            'medium' => 'M j, Y',
            'long' => 'F j, Y',
            'full' => 'l, F j, Y',
        ];
        $timeFormatMap = [
            'none' => '',
            'short' => 'g:i A',
            'medium' => 'g:i:s A',
            'long' => 'g:i:s A T',
            'full' => 'g:i:s A e',
        ];

        $dateFormat = $dateFormatMap[$dateStyle] ?? $dateFormatMap['medium'];
        $timeFormat = $timeFormatMap[$timeStyle] ?? $timeFormatMap['short'];

        $combinedFormat = trim($dateFormat . ($dateFormat && $timeFormat ? ' ' : '') . $timeFormat);
        if ($combinedFormat === '') {
            $combinedFormat = 'M j, Y g:i A';
        }

        return $dateForFormat->format($combinedFormat);
    }
}
