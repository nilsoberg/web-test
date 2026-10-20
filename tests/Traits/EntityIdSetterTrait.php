<?php

namespace App\Tests\Traits;

trait EntityIdSetterTrait
{
    /**
     * Sets a private or protected ID on an entity using reflection.
     */
    protected function setEntityId(object $entity, int $id, string $idProperty = 'id'): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty($idProperty);
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
