<?php
declare(strict_types=1);

namespace App\Preview;

class Streamer
{
    /**
     * Stream a physical file supporting HTTP 206 Partial Content (Range Requests)
     * and safe chunked streaming without exceeding PHP memory.
     */
    public static function stream(
        string $filePath,
        string $mimeType,
        string $downloadName = '',
        bool $isAttachment = false,
        int $chunkSize = 65536
    ): void {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        $size = filesize($filePath);
        $time = filemtime($filePath);
        $fmTime = gmdate('D, d M Y H:i:s', $time) . ' GMT';

        // Clean any active PHP output buffers to prevent memory overflow
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $fp = fopen($filePath, 'rb');
        if (!$fp) {
            http_response_code(500);
            echo 'Failed to open file.';
            exit;
        }

        // Set baseline security & caching headers
        header('X-Content-Type-Options: nosniff');
        header('Last-Modified: ' . $fmTime);
        header('Cache-Control: private, max-age=3600');
        header('Accept-Ranges: bytes');

        $dispositionType = $isAttachment ? 'attachment' : 'inline';
        $safeFilename = str_replace('"', '', $downloadName ?: basename($filePath));
        header("Content-Disposition: {$dispositionType}; filename=\"{$safeFilename}\"");

        // Handle HTTP Range Requests (Essential for seeking in MP4 / WebM / MP3)
        $start = 0;
        $end = $size - 1;
        $httpRange = $_SERVER['HTTP_RANGE'] ?? null;

        if ($httpRange && preg_match('/bytes=\s*(\d+)-(\d*)[\s,]*$/i', $httpRange, $matches)) {
            $start = (int)$matches[1];
            if (!empty($matches[2])) {
                $end = min((int)$matches[2], $size - 1);
            }

            if ($start > $end || $start >= $size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes */{$size}");
                fclose($fp);
                exit;
            }

            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes {$start}-{$end}/{$size}");
            $length = $end - $start + 1;
        } else {
            header('HTTP/1.1 200 OK');
            $length = $size;
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string)$length);

        // Stream chunk by chunk
        fseek($fp, $start);
        $remaining = $length;

        // Prevent script timeout during large file stream
        @set_time_limit(0);

        while (!feof($fp) && $remaining > 0 && connection_status() === CONNECTION_NORMAL) {
            $readLength = min($chunkSize, $remaining);
            $buffer = fread($fp, $readLength);
            if ($buffer === false) {
                break;
            }
            echo $buffer;
            flush();
            $remaining -= strlen($buffer);
        }

        fclose($fp);
        exit;
    }
}
