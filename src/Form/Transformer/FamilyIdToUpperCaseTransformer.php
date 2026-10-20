<?php

namespace App\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

class FamilyIdToUpperCaseTransformer implements DataTransformerInterface
{
    public function transform($value): ?string
    {
        return $value;
    }

    public function reverseTransform($value): ?string
    {
        if (null === $value) {
            return null;
        }

        return mb_strtoupper($value);
    }
}
