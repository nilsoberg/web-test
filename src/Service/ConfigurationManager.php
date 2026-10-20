<?php

namespace App\Service;

use App\Config\Settings;
use App\Entity\AppSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ConfigurationManager
{
    private $localCache = null;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly EntityManagerInterface $em,
        private readonly ParameterBagInterface $parameterBag,
        private readonly string $defaultDbVersion,
        private readonly string $uploadsDirectory,
    )
    {
    }

    /**
     * Get a setting by key, falling back to services.yaml if missing from DB
     */
    public function get(string $key, mixed $defaultFallback = null): mixed
    {
        $allSettings = $this->loadAllSettings();

        if (array_key_exists($key, $allSettings)) {
            return $allSettings[$key];
        }

        // Check if any values passed into the constructor and autowired match
        $autowireValue = $this->checkAutowires($key);
        if ($autowireValue !== null) {
            return $autowireValue;
        }

        // Fall back to services.yaml parameter if it exists
        $parameterKey = 'app.' . $key;
        if ($this->parameterBag->has($parameterKey)) {
            return $this->parameterBag->get($parameterKey);
        }

        return $defaultFallback;
    }

    /**
     * Mutate a configuration setting at runtime (e.g. from an admin form)
     */
    public function set(string $key, ?string $value): void
    {
        $setting = $this->em->getRepository(AppSetting::class)->find($key) ?? new AppSetting();
        $setting->setSettingKey($key);
        $setting->setSettingValue($value);

        $this->em->persist($setting);
        $this->em->flush();

        // Delete the cache if we update it
        $this->cache->delete('app_system_settings');
        $this->localCache = null;
    }

    private function loadAllSettings(): array
    {
        // Return request-local memory if already fetched during this execution thread
        if ($this->localCache !== null) {
            return $this->localCache;
        }

        // Fetch from shared cache, caching it for 1 hour (3600s)
        $this->localCache = $this->cache->get('app_system_settings', function (ItemInterface $item) {
            $item->expiresAfter(3600); 

            $settings = $this->em->getRepository(AppSetting::class)->findAll();
            $mapped = [];
            
            foreach ($settings as $setting) {
                $mapped[$setting->getSettingKey()] = $setting->getSettingValue();
            }

            return $mapped; // This array gets saved directly to the cache store
        });

        return $this->localCache;
    }

    private function checkAutowires(string $key): ?string
    {
        if ($key === Settings::DEFAULT_DATABASE_VERSION) {
            return $this->defaultDbVersion;
        } else if ($key === Settings::UPLOADS_DIRECTORY) {
            return $this->uploadsDirectory;
        }

        return null;
    }
}
