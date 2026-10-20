<?php

namespace App\Tests\EventListener;

use App\Attribute\RateLimit;
use App\EventListener\RateLimitListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit as RateLimitResult;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class RateLimitListenerTest extends TestCase
{
    private RateLimiterFactoryInterface&MockObject $contactFormLimiter;
    private RateLimiterFactoryInterface&MockObject $authSubmissionLimiter;
    private RateLimiterFactoryInterface&MockObject $jobSubmissionLimiter;
    private LimiterInterface&MockObject $limiter;
    private RateLimitListener $listener;

    protected function setUp(): void
    {
        $this->contactFormLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $this->authSubmissionLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $this->jobSubmissionLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $this->limiter = $this->createMock(LimiterInterface::class);

        $this->listener = new RateLimitListener(
            $this->contactFormLimiter,
            $this->authSubmissionLimiter,
            $this->jobSubmissionLimiter
        );
    }

    public function testIgnoresSubRequests(): void
    {
        $event = $this->createEvent(isMainRequest: false);

        $this->contactFormLimiter->expects($this->never())->method('create');
        
        ($this->listener)($event);

        $this->assertNull($event->getResponse());
    }

    public function testIgnoresSafeHttpMethods(): void
    {
        $request = Request::create('/feedback', 'GET');
        $event = $this->createEvent(request: $request);

        $this->contactFormLimiter->expects($this->never())->method('create');

        ($this->listener)($event);

        $this->assertNull($event->getResponse());
    }

    public function testBlocksRequestWhenRateLimitExceeded(): void
    {
        $request = Request::create('/feedback', 'POST', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);
        $request->attributes->set('_route', 'feedback');

        $event = $this->createEvent(request: $request);

        $this->contactFormLimiter
            ->expects($this->once())
            ->method('create')
            ->with('192.168.1.1')
            ->willReturn($this->limiter);

        // Instantiate real RateLimit object configured as NOT accepted (0 remaining tokens)
        $rateLimitResult = new RateLimitResult(
            availableTokens: 0,
            retryAfter: new \DateTimeImmutable('+60 seconds'),
            accepted: false,
            limit: 5
        );

        $this->limiter
            ->expects($this->once())
            ->method('consume')
            ->with(1)
            ->willReturn($rateLimitResult);

        ($this->listener)($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame('Too many requests. Please wait a moment and try again.', $response->getContent());
    }

    public function testAppLoginRouteTriggersAuthLimiter(): void
    {
        $request = Request::create('/login', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $request->attributes->set('_route', 'app_login');

        $event = $this->createEvent(request: $request);

        $this->authSubmissionLimiter
            ->expects($this->once())
            ->method('create')
            ->with('127.0.0.1')
            ->willReturn($this->limiter);

        // Instantiate real RateLimit object configured as accepted
        $rateLimitResult = new RateLimitResult(
            availableTokens: 4,
            retryAfter: new \DateTimeImmutable(),
            accepted: true,
            limit: 5
        );

        $this->limiter
            ->expects($this->once())
            ->method('consume')
            ->with(1)
            ->willReturn($rateLimitResult);

        ($this->listener)($event);

        $this->assertNull($event->getResponse());
    }

    public function testResolvesLimiterFromControllerAttribute(): void
    {
        $request = Request::create('/custom-job', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
        $request->attributes->set('_route', 'custom_job_route');
        
        $controller = [new DummyJobController(), 'submitJob'];
        $request->attributes->set('_controller', $controller);

        $event = $this->createEvent(request: $request);

        $this->jobSubmissionLimiter
            ->expects($this->once())
            ->method('create')
            ->with('10.0.0.1')
            ->willReturn($this->limiter);

        $rateLimitResult = new RateLimitResult(
            availableTokens: 4,
            retryAfter: new \DateTimeImmutable(),
            accepted: true,
            limit: 5
        );

        $this->limiter
            ->expects($this->once())
            ->method('consume')
            ->with(1)
            ->willReturn($rateLimitResult);

        ($this->listener)($event);

        $this->assertNull($event->getResponse());
    }

    private function createEvent(?Request $request = null, bool $isMainRequest = true): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $request ?? Request::create('/feedback', 'POST');
        $requestType = $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, $request, $requestType);
    }
}

class DummyJobController
{
    #[RateLimit(limiter: 'job_submission')]
    public function submitJob(): void
    {
    }
}
