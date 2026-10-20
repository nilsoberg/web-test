<?php

namespace App\Entity;

use Attribute;
use ReflectionClass;

#[Attribute(Attribute::TARGET_CLASS)]
class DisplayName
{
    public function __construct(
        public string $name
    ) {}

    /**
     * Helper method to get the display name from any object or class string.
     */
    public static function getFrom(object|string $classOrObject): ?string
    {
        try {
            $reflection = new ReflectionClass($classOrObject);
            $attributes = $reflection->getAttributes(self::class);

            if (count($attributes) > 0) {
                // Return the 'name' property from the first found attribute
                return $attributes[0]->newInstance()->name;
            }
        } catch (\ReflectionException $e) {
            // Class does not exist or other reflection error
            return null;
        }

        return null;
    }
}
