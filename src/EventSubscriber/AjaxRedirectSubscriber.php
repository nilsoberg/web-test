<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AjaxRedirectSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Listen right before the response is sent to the browser
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * Listen to events from the kernel.  If the request is an AJAX XHR request and the response
     * is a standard redirect, then this was likely a file upload event.  We need to send the URL
     * via JSON rather than a normal redirect response.  That will allow us to use JavaScript to
     * do the redirect, avoiding double requests.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // If it's an AJAX request and the controller triggered a standard redirect, intercept
        // the response and send a 200 JSON response with the target URL instead.
        if ($request->isXmlHttpRequest() && $response instanceof RedirectResponse) {
            $event->setResponse(new JsonResponse([
                'redirect' => $response->getTargetUrl()
            ]));
        }
    }
}
