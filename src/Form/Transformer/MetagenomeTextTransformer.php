<?php

namespace App\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

class MetagenomeTextTransformer implements DataTransformerInterface
{
    /**
     * Transforms an array (from Entity) to a string (for Textarea display).
     */
    public function transform(mixed $value): string
    {
        if (empty($value) || !is_array($value)) {
            return '';
        }

        $lines = [];

        $header = array_keys($value[0]);
        $lines[] = implode("\t", $header);

        foreach ($value as $item) {
            $lines[] = implode("\t", $item);
        }

        return implode("\n", $lines);
    }

    /**
     * Transforms a pasted string (from Textarea) to an array (for Entity/JSON).
     */
    public function reverseTransform(mixed $value): array
    {
        if (null === $value || trim($value) === '') {
            return [];
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $value));
        $result = [];
        $header = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);

            if (empty($header)) {
                $header = array_map(fn(string $value): string => $value, $parts);
                continue;
            } 

            if (count($parts) >= count($header)) {
                $data = [];
                foreach ($header as $idx => $col) {
                    $data[$col] = isset($parts[$idx]) ? $parts[$idx] : '-';
                }
                $result[] = $data;
            }
        }

        return $result;
    }
}
