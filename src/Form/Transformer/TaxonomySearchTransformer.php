<?php

namespace App\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms data between an array (for the form) and a JSON string (for the entity).
 */
class TaxonomySearchTransformer implements DataTransformerInterface
{
    /**
     * Transforms the entity's JSON string into an array for the form.
     *
     * @param  string|null $jsonString The value from the entity.
     * @return array The value for the form field.
     */
    public function transform($jsonString): array
    {
        if (null === $jsonString || '' === $jsonString) {
            return [];
        }

        $data = json_decode($jsonString, true);

        // Check for decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new TransformationFailedException('Failed to decode JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Transforms the form's array into a JSON string for the entity.
     *
     * @param  array|null $array The value from the form submission.
     * @return string|null The value to store in the entity.
     */
    public function reverseTransform($array): ?string
    {
        // Return null if the collection is empty to store NULL in the database.
        if (null === $array || [] === $array) {
            return null;
        }

        return json_encode($array);
    }
}
