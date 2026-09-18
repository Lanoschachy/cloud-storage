<?php
declare(strict_types=1);

namespace App\Helpers;

class Format
{
    /**
     * Format bytes to human-readable string (KB, MB, GB, TB).
     */
    public static function bytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Format date to standard user-friendly string.
     */
    public static function dateTime(string $isoDate): string
    {
        $timestamp = strtotime($isoDate);
        if ($timestamp === false) {
            return $isoDate;
        }
        return date('d M Y, H:i', $timestamp);
    }
}
