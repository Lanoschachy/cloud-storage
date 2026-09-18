<?php
declare(strict_types=1);

namespace App\Preview;

use App\Preview\Streamer;

class PreviewManager
{
    /**
     * Map MIME type to preview category.
     */
    public static function getPreviewType(string $mimeType, string $extension): string
    {
        $mimeType = strtolower($mimeType);
        $ext = strtolower($extension);

        // Images
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']) || str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        // Videos
        if (in_array($ext, ['mp4', 'webm', 'ogg']) || str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        // Audio
        if (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'aac']) || str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        // PDF
        if ($ext === 'pdf' || $mimeType === 'application/pdf') {
            return 'pdf';
        }

        // Code and Text
        $codeExts = ['txt', 'csv', 'json', 'xml', 'md', 'html', 'css', 'js', 'php', 'py', 'sh', 'sql', 'yaml', 'yml', 'ini', 'log'];
        if (in_array($ext, $codeExts) || str_starts_with($mimeType, 'text/') || $mimeType === 'application/json' || $mimeType === 'application/xml') {
            return 'text';
        }

        // Office
        $officeExts = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'];
        if (in_array($ext, $officeExts)) {
            return 'office';
        }

        return 'unsupported';
    }

    /**
     * Serve content stream.
     */
    public static function serve(array $file, string $physicalPath, bool $isAttachment = false): void
    {
        $mime = $file['mime_type'] ?? 'application/octet-stream';
        $ext = $file['extension'] ?? '';
        $name = $file['name'] ?? 'download';

        // Additional MIME safety for SVGs to prevent script injection in origin
        if (strtolower($ext) === 'svg' || $mime === 'image/svg+xml') {
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
        }

        Streamer::stream($physicalPath, $mime, $name, $isAttachment);
    }

    /**
     * Get text content safely for preview.
     */
    public static function readTextContent(string $physicalPath, int $maxBytes = 2097152): string
    {
        if (!file_exists($physicalPath) || !is_readable($physicalPath)) {
            return 'Error: File tidak dapat dibaca.';
        }

        $size = filesize($physicalPath);
        if ($size > $maxBytes) {
            $fp = fopen($physicalPath, 'rb');
            $content = fread($fp, $maxBytes);
            fclose($fp);
            return $content . "\n\n[...Konten dipotong karena melebihi batas pratinjau teks (" . round($maxBytes / 1024 / 1024, 1) . " MB)...]";
        }

        return file_get_contents($physicalPath) ?: '';
    }
}
