<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\FileRepository;
use App\Repositories\SettingsRepository;

class StorageUsageService
{
    protected FileRepository $fileRepo;
    protected SettingsRepository $settingsRepo;
    protected string $storagePath;

    public function __construct(FileRepository $fileRepo, SettingsRepository $settingsRepo, string $storagePath)
    {
        $this->fileRepo = $fileRepo;
        $this->settingsRepo = $settingsRepo;
        $this->storagePath = $storagePath;
    }

    public function getUsage(): array
    {
        $settings = $this->settingsRepo->getSettings();
        $usedBytes = $this->fileRepo->getTotalSize();

        $mode = $settings['storage_quota_mode'] ?? 'disk_free';
        $totalBytes = 0;
        $availableBytes = 0;

        if ($mode === 'custom' && !empty($settings['custom_quota_bytes'])) {
            $totalBytes = (int)$settings['custom_quota_bytes'];
            $availableBytes = max(0, $totalBytes - $usedBytes);
        } else {
            // Attempt reading filesystem quota
            $diskTotal = @disk_total_space($this->storagePath);
            $diskFree = @disk_free_space($this->storagePath);

            if ($diskTotal !== false && $diskFree !== false) {
                $totalBytes = (int)$diskTotal;
                $availableBytes = (int)$diskFree;
            } else {
                // Fallback to custom 10GB if disk functions are restricted in cPanel
                $totalBytes = 10 * 1024 * 1024 * 1024;
                $availableBytes = max(0, $totalBytes - $usedBytes);
            }
        }

        $percent = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 1) : 0;
        $source = 'Server Disk / Filesystem';
        $warning = 'Kapasitas dihitung dari partisi server hosting dan mungkin berbeda dengan batasan paket cPanel Anda.';

        if ($mode === 'custom' && !empty($settings['custom_quota_bytes'])) {
            $source = 'Konfigurasi Internal';
            $warning = 'Kapasitas dibatasi sesuai konfigurasi internal.';
        }

        return [
            'used_bytes' => $usedBytes,
            'total_bytes' => $totalBytes,
            'available_bytes' => $availableBytes,
            'used_percent' => min(100, $percent),
            'quota_source' => $source,
            'quota_warning' => $warning,
            'quota_mode' => $mode,
        ];
    }

    public function canAcceptUpload(int $incomingBytes): bool
    {
        $usage = $this->getUsage();
        return $incomingBytes <= $usage['available_bytes'];
    }
}
