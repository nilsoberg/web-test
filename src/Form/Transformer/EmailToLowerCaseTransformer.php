<?php

namespace App\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

class EmailToLowerCaseTransformer implements DataTransformerInterface
{
    public function transform($value): ?string
    {
        return $value;
    }

    public function reverseTransform($value): ?string
    {
        return $value ? mb_strtolower($value) : null;
    }
}
