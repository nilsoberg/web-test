<?php

namespace App\EventListener;

use App\Attribute\RateLimit;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class RateLimitListener
{
    public function __construct(
        #[Target('contactFormLimiter')]
        private RateLimiterFactoryInterface $contactFormLimiter,
        #[Target('authSubmissionLimiter')]
        private RateLimiterFactoryInterface $authSubmissionLimiter,
        #[Target('jobSubmissionLimiter')]
        private RateLimiterFactoryInterface $jobSubmissionLimiter,
    )
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip GET / HEAD
        if ($request->isMethodSafe()) {
            return;
        }

        $route = $request->attributes->get('_route');
        if (!$route) {
            return;
        }

        $limiterName = match ($route) {
            'app_login' => $this->authSubmissionLimiter,
            'feedback' => $this->contactFormLimiter,
            default => $this->resolveLimiterFromAttribute($request),
        };

        if ($limiterName === null) {
            return;
        }

        $rateLimiter = $limiterName->create($request->getClientIp());

        if (false === $rateLimiter->consume(1)->isAccepted()) {
            $event->setResponse(new Response(
                'Too many requests. Please wait a moment and try again.',
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Content-Type' => 'text/plain']
            ));
        }
    }

    private function resolveLimiterFromAttribute(Request $request): ?RateLimiterFactoryInterface
    {
        $controller = $request->attributes->get('_controller');
        if (!is_string($controller) && !is_array($controller)) {
            return null;
        }

        try {
            if (is_array($controller)) {
                $reflection = new \ReflectionMethod($controller[0], $controller[1]);
            } elseif (is_string($controller) && str_contains($controller, '::')) {
                [$class, $method] = explode('::', $controller, 2);
                $reflection = new \ReflectionMethod($class, $method);
            } else {
                $reflection = new \ReflectionClass($controller);
            }
        } catch (\ReflectionException $e) {
            return null;
        }

        $attributes = $reflection->getAttributes(RateLimit::class);
        if (empty($attributes)) {
            return null;
        }

        /** @var RateLimit $config */
        $config = $attributes[0]->newInstance();

        return match ($config->limiter) {
            'contact_form' => $this->contactFormLimiter,
            'auth_submission' => $this->authSubmissionLimiter,
            'job_submission' => $this->jobSubmissionLimiter,
            default => null,
        };
    }
}
