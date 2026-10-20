<?php

namespace App\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

class MetagenomeMetadataTransformer implements DataTransformerInterface
{
    /**
     * Transforms an array (from Entity) to a string (for Textarea display).
     */
    public function transform(mixed $value): string
    {
        if (empty($value) || !is_array($value)) {
            return '';
        }

        return json_encode($value, \JSON_PRETTY_PRINT);
    }

    /**
     * Transforms a pasted string (from Textarea) to an array (for Entity/JSON).
     */
    public function reverseTransform(mixed $value): array
    {
        if (null === $value || trim($value) === '') {
            return [];
        }

        $json = ltrim($value, "\xEF\xBB\xBF");
        $json = mb_convert_encoding($json, 'UTF-8', 'UTF-8');
        $json = preg_replace('/[\x00-\x1F\x7F]/u', '', $json);

        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}
