<?php

namespace App\EventListener;

use App\Auth\Entity\UserEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
class ForcePasswordChangeListener
{
    private const ALLOWED_ROUTES = [
        'account_change_password',
        'auth_logout',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly RouterInterface $router,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof UserEntity || !$user->getMustChangePassword()) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if ($route === '' || in_array($route, self::ALLOWED_ROUTES, true) || str_starts_with($route, '_')) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('account_change_password')));
    }
}
