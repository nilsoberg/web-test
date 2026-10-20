<?php

namespace App\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

class FamilySeparatorTransformer implements DataTransformerInterface
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

        // Don't do full sanitization here.  Invalid characters (not alphanumeric, spaces and
        // commas) are kept here, and the constraint system processes those and the form is
        // kicked back to the user.

        // Remove any spaces or commas at the start or end of the string
        $trimmed = trim($value, " \t\n\r\0\x0B,");

        // Replace consecutive spaces or commas with a single comma
        $commasFixed = preg_replace('/[\s,]+/', ',', $trimmed);

        return $commasFixed;
    }
}
