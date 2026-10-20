<?php

namespace App\EventListener;

use App\Config\Settings;
use App\Repository\DatabaseMetadataRepository;
use App\Service\ConfigurationManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsEventListener(event: 'kernel.request')]
class AdminConfigWarningListener
{
    private ?bool $isConfigValid = null;

    public function __construct(
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly CacheInterface $cache,
        private readonly ConfigurationManager $config,
        private readonly DatabaseMetadataRepository $dbRepo,
        private readonly RequestStack $requestStack,
    )
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        // Only run on main HTML requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->isXmlHttpRequest() || $request->getRequestFormat() !== 'html') {
            return;
        }

        // Early exit for non-admin users (optimization)
        if (!$this->authChecker->isGranted('ROLE_DATA_ADMIN')) {
            return;
        }

        // Cache the validation check across requests
        if ($this->isConfigValid === null) {
            $this->isConfigValid = $this->cache->get(Settings::CACHE_ADMIN_CONFIG_VALID_KEY, function (ItemInterface $item) {
                $item->expiresAfter(300); // Cache for 5 minutes (also invalidate on database config save)

                $defaultDbVersion = $this->config->get(Settings::DEFAULT_DATABASE_VERSION);
                if (!$defaultDbVersion) {
                    return false;
                }

                $defaultDb = $this->dbRepo->findOneBy(['dbName' => $defaultDbVersion]);
                return $defaultDb !== null;
            });
        }

        // Add flash warning if configuration is invalid
        if (!$this->isConfigValid) {
            $session = $this->requestStack->getSession();
            $flashBag = $session->getFlashBag();

            $warningText = 'The default database version specified in the configuration file is not present in the database.';

            if (!in_array($warningText, $flashBag->peek('danger'), true)) {
                $flashBag->add('danger', $warningText);
            }
        }
    }
}
