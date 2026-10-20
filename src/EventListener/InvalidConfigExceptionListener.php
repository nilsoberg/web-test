<?php

namespace App\EventListener;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: 'kernel.exception')]
class InvalidConfigExceptionListener
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof InvalidConfigurationException) {
            return;
        }

        $session = $this->requestStack->getSession();
        $session->getFlashBag()->add(
            'error', 
            'Unable to process request. Please contact an administrator.'
        );

        // Redirect back to referring page or fallback to home/dashboard
        $request = $event->getRequest();
        $referer = $request->headers->get('referer');
        $targetUrl = $referer ?: $this->urlGenerator->generate('home');

        $event->setResponse(new RedirectResponse($targetUrl));
    }
}
