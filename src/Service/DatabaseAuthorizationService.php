<?php

namespace App\Service;

use App\Auth\Entity\UserEntity;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class DatabaseAuthorizationService
{
    public function __construct(
        private readonly DatabaseListService $databaseService,
        private readonly TagAwareCacheInterface $cache,
    )
    {
    }

    /**
     * Checks if a user has access to a specific sequence database ID.
     */
    public function isGranted(UserEntity $user, int $databaseId): bool
    {
        $cacheKey = sprintf('user_db_access_%d', $user->getId());

        // Cache the array of allowed IDs for 1 hour (or until invalidated)
        $allowedDbIds = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(3600); // 1 hour
            $item->tag(self::makeCacheTagKey($user));

            // Delegate to slow authorization resolver service once
            return $this->databaseService->getAllowedDatabaseIdsForUser($user);
        });

        return in_array($databaseId, $allowedDbIds, true);
    }

    public function invalidateCachedUser(UserEntity $user): void
    {
        $this->cache->invalidateTags([self::makeCacheTagKey($user)]);
    }

    private static function makeCacheTagKey(UserEntity $user): string
    {
        return sprintf('user_access_%d', $user->getId());
    }
}
