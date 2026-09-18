<?php
declare(strict_types=1);

namespace App\Repositories;

class SettingsRepository extends JsonRepository
{
    public function __construct(string $dataPath)
    {
        parent::__construct($dataPath . DIRECTORY_SEPARATOR . 'settings.json');

        $settings = $this->read();
        if (empty($settings)) {
            $this->write([
                'app_name' => 'Personal Cloud Storage',
                'theme' => 'light',
                'view_mode' => 'grid', // 'grid' or 'list'
                'sort_by' => 'name',   // 'name', 'size', 'updated_at'
                'sort_order' => 'asc', // 'asc' or 'desc'
                'trash_retention_days' => 30,
                'storage_quota_mode' => 'disk_free', // 'disk_free' or 'custom'
                'custom_quota_bytes' => 10 * 1024 * 1024 * 1024, // 10 GB default
            ]);
        }
    }

    public function getSettings(): array
    {
        return $this->read();
    }

    public function updateSettings(array $newSettings): bool
    {
        return $this->mutate(function (&$settings) use ($newSettings) {
            $settings = array_merge($settings, $newSettings);
            return true;
        }) !== false;
    }
}
