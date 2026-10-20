<?php

namespace App\Twig\Extension;

use App\Config\Constants;
use ReflectionClass;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class ConstantsExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * This method makes the constants available globally in all Twig templates.
     */
    public function getGlobals(): array
    {
        // Use PHP's ReflectionClass to get all constants from the Constants class
        $reflectionClass = new ReflectionClass(Constants::class);

        // The value is an array of all the constants (e.g., ['MY_CONST' => 'value']).
        return [
            'app_constants' => $reflectionClass->getConstants(),
        ];
    }
}
