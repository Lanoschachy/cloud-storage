<?php
declare(strict_types=1);

namespace App\Repositories;

class JsonRepository
{
    protected string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        if (!file_exists($filePath)) {
            // Initialize empty JSON array or object
            $this->atomicWrite([]);
        }
    }

    /**
     * Read and decode JSON file with shared lock.
     */
    public function read(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $fp = fopen($this->filePath, 'rb');
        if (!$fp) {
            return [];
        }

        flock($fp, LOCK_SH);
        $size = filesize($this->filePath);
        $content = $size > 0 ? fread($fp, $size) : '';
        flock($fp, LOCK_UN);
        fclose($fp);

        if (empty($content)) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Write data atomically to avoid file corruption during concurrent operations.
     */
    public function write(array $data): bool
    {
        return $this->atomicWrite($data);
    }

    /**
     * Mutate data safely inside exclusive lock.
     */
    public function mutate(callable $callback): mixed
    {
        $lockFile = $this->filePath . '.lock';
        $lockFp = fopen($lockFile, 'c+');
        if (!$lockFp) {
            return false;
        }

        flock($lockFp, LOCK_EX);

        try {
            $data = $this->read();
            $result = $callback($data);
            if ($result !== false && is_array($data)) {
                $this->atomicWrite($data);
            }
            return $result;
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    /**
     * Perform atomic write via temporary file and rename.
     */
    protected function atomicWrite(array $data): bool
    {
        $dir = dirname($this->filePath);
        $tempFile = tempnam($dir, 'tmp_json_');
        if ($tempFile === false) {
            return false;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            @unlink($tempFile);
            return false;
        }

        $fp = fopen($tempFile, 'wb');
        if (!$fp) {
            @unlink($tempFile);
            return false;
        }

        fwrite($fp, $json);
        fflush($fp);
        fclose($fp);

        // Atomic rename
        $renamed = @rename($tempFile, $this->filePath);
        if (!$renamed) {
            // Windows fallback or cross-filesystem issue
            @unlink($this->filePath);
            $renamed = @rename($tempFile, $this->filePath);
        }

        @chmod($this->filePath, 0640);
        return $renamed;
    }
}
