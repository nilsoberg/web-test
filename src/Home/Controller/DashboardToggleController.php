<?php

namespace App\Home\Controller;

use App\Attribute\RateLimit;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardToggleController extends AbstractController
{
    const DASHBOARD_COOKIE_NAME = 'dashboard_style';

    #[Route('/toggle-dashboard/{style}', name: 'app_dashboard_toggle')]
    #[RateLimit(limiter: 'auth_submission')]
    public function toggle(string $style, Request $request): Response
    {
        $allowedStyles = ['old', 'new'];
        if (!in_array($style, $allowedStyles, true)) {
            $style = 'new';
        }

        $targetUrl = $this->getSafeRedirectUrl($request);

        $response = $this->redirect($targetUrl);

        $cookie = Cookie::create(self::DASHBOARD_COOKIE_NAME)
            ->withValue($style)
            ->withExpires(new \DateTime('+10 years'))
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite('lax');

        $response->headers->setCookie($cookie);

        return $response;
    }

    private function getSafeRedirectUrl(Request $request): string
    {
        $fallback = $this->generateUrl('home');

        // Determine where the user came from
        $referer = $request->headers->get('referer', '');

        if ($referer === '') {
            return $fallback;
        }

        $components = parse_url($referer);

        // If parsing fails or the referer URL contains user info, then reject the request and
        // redirect to the fallback
        if (!$components || isset($components['user']) || isset($components['pass'])) {
            return $fallback;
        }

        // Extract the components of the URL for validation
        $scheme = strtolower($components['scheme'] ?? '');
        $host = strtolower($components['host'] ?? '');
        $port = isset($components['port'])
            ? (int)$components['port']
            : ($scheme === 'https' ? 443 :80);

        // Verify that the referer and the request match
        if ($scheme !== strtolower($request->getScheme()) ||
            $host !== strtolower($request->getHost()) ||
            $port !== (int) $request->getPort()
        ) {
            return $fallback;
        }

        return $referer;
    }
}
