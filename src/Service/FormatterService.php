<?php

namespace App\Service;

class FormatterService
{
    /**
     * Formats file size into a human-readable string in either KB or MB.  The output is rounded
     * to the hundredth place.
     * @param {int} $bytes - size in bytes
     * @return {string} formatted string (e.g. '3.01 MB')
     */
    public function formatSizeUnits(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Formats file size into a human-readable string, using MB as the units.  The output is
     * rounded to the ones place.
     * @param {int} $bytes - size in bytes
     * @return {string} formatted string (e.g. '3 MB' or '< 1 MB')
     */
    public function formatSizeUnitsMb(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576) . ' MB';
        } else {
            return '< 1 MB';
        }
    }

    /**
     * Formats file size into a human-readable string.  If the size is greater than 1 GB, uses
     * GB as the unit rounded to the tenth place, otherwise uses MB as the units.  The output is
     * rounded to the ones place.
     * @param {int} $bytes - size in bytes
     * @return {string} formatted string (e.g. '3.1 GB' or '891 MB')
     */
    public function formatSizeUnitsMbGb(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        } else {
            return $this->formatSizeUnitsMb($bytes);
        }
    }
}
