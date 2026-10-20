<?php

namespace App\Tests\Controller;

use App\Auth\Entity\UserEntity;
use App\Home\Controller\DashboardToggleController;
use App\Tests\BaseControllerTestCase;
use App\Tests\Factory\UserEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Browser\Test\HasBrowser;
use Zenstruck\Foundry\Test\Factories;

class DashboardToggleControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    /**
     * Test that a valid style sets the cookie and redirects safely.
     */
    public function testToggleWithValidStyle(): void
    {
        $user = UserEntityFactory::createOne();
        $referer = 'https://localhost/efi-est';

        $this->browser()
            ->actingAs($user)
            ->interceptRedirects()
            ->get('/toggle-dashboard/old', [ 'headers' => [ 'Referer' => $referer ], 'server' => ['HTTPS' => true] ])
            ->assertRedirectedTo($referer)
            ->use(function(\Symfony\Component\BrowserKit\CookieJar $cookieJar) {
                $cookie = $cookieJar->get(DashboardToggleController::DASHBOARD_COOKIE_NAME);
                $this->assertNotNull($cookie, 'Cookie "' . DashboardToggleController::DASHBOARD_COOKIE_NAME . '" not found');
                $this->assertSame('old', $cookie->getValue());
            });
    }

    /**
     * Verify that there is no open redirect.
     * If referer is external, it should fallback to a safe internal route.
     */
    public function testTogglePreventsOpenRedirect(): void
    {
        $user = UserEntityFactory::createOne();

        // External/Malicious referer
        $externalReferer = 'https://example.com/invalid-referer';
        $expectedUrl = $this->getContainer()->get('router')->generate('home');

        // Verify that it redirects back to the default referer and not the external/invalid site
        $this->browser()
            ->actingAs($user)
            ->interceptRedirects()
            ->get('/toggle-dashboard/new', [ 'headers' => [ 'Referer' => $externalReferer ], 'server' => ['HTTPS' => true] ])
			->assertRedirectedTo($expectedUrl)
            ->use(function(\Symfony\Component\BrowserKit\CookieJar $cookieJar) {
                $cookie = $cookieJar->get(DashboardToggleController::DASHBOARD_COOKIE_NAME);
                $this->assertNotNull($cookie, 'Cookie "' . DashboardToggleController::DASHBOARD_COOKIE_NAME . '" not found');
                $this->assertSame('new', $cookie->getValue());
            });
    }

    /**
     * Test missing referer fallback.
     */
    public function testToggleFallbackWhenRefererIsMissing(): void
    {
        $user = UserEntityFactory::createOne();

        $expectedUrl = $this->getContainer()->get('router')->generate('home');

        // Verify that it redirects back to the default referer and not the external/invalid site
        $this->browser()
            ->actingAs($user)
            ->interceptRedirects()
            ->get('/toggle-dashboard/new', [ 'server' => ['HTTPS' => true] ])
			->assertRedirectedTo($expectedUrl)
            ->use(function(\Symfony\Component\BrowserKit\CookieJar $cookieJar) {
                $cookie = $cookieJar->get(DashboardToggleController::DASHBOARD_COOKIE_NAME);
                $this->assertNotNull($cookie, 'Cookie "' . DashboardToggleController::DASHBOARD_COOKIE_NAME . '" not found');
                $this->assertSame('new', $cookie->getValue());
            });
    }

    /**
     * Test style validation (Invalid input).
     */
    public function testToggleValidatesStyleInput(): void
    {
        $user = UserEntityFactory::createOne();

        // Verify that it redirects back to the default referer and not the external/invalid site
        $this->browser()
            ->actingAs($user)
            ->get('/toggle-dashboard/invalid-option', [ 'server' => ['HTTPS' => true] ])
            ->use(function(\Symfony\Component\BrowserKit\CookieJar $cookieJar) {
                $cookie = $cookieJar->get(DashboardToggleController::DASHBOARD_COOKIE_NAME);
                $this->assertNotNull($cookie, 'Cookie "' . DashboardToggleController::DASHBOARD_COOKIE_NAME . '" not found');
                $this->assertSame('new', $cookie->getValue());
            });
    }

    /**
     * Verify that a user-info bypass (e.g. https://mysite.com@malicious.com) is rejected.
     */
    public function testTogglePreventsUserInfoBypass(): void
    {
        $user = UserEntityFactory::createOne();

        $localReferer = 'https://localhost';
        $expectedUrl = $this->getContainer()->get('router')->generate('home');

        // The malicious referer that looks like our site but isn't
        $maliciousReferer = $localReferer . '@malicious-domain.com/login';

        $this->browser()
            ->actingAs($user)
            ->interceptRedirects()
            ->get('/toggle-dashboard/new', [ 'headers' => [ 'Referer' => $maliciousReferer ], 'server' => ['HTTPS' => true] ])
			->assertRedirectedTo($expectedUrl);
    }
}
