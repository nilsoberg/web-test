<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;
use Twig\Extension\CoreExtension;

#[AsEventListener(event: KernelEvents::REQUEST, priority: -1)]
class UserContextListener
{
    public function __construct(
        private Environment $twig,
        private Security $security,
        private string $defaultTimezone,
        private string $defaultLocale,
    )
	{
	}

    public function onKernelRequest(RequestEvent $event): void
    {
        // Skip sub-requests (only apply to the main page load)
        if (!$event->isMainRequest()) {
            return;
        }

        // Default to timezone speficied in the config files
        $timezone = $this->defaultTimezone;

        $user = $this->security->getUser();

        // If user is logged in and has a timezone, use it
        if ($user && method_exists($user, 'getTimezone') && $user->getTimezone()) {
            $timezone = $user->getTimezone();
        }

        // Inject it into Twig globally so all date/times are rendered with the given timezone
        $this->twig->getExtension(CoreExtension::class)->setTimezone($timezone);

        $locale = $this->defaultLocale;
        if ($user && method_exists($user, 'getLocale') && $user->getLocale()) {
            $locale = $user->getLocale();
        }
        $event->getRequest()->setLocale($locale);
    }
}
