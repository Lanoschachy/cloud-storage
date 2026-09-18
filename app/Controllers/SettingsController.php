<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SettingsRepository;
use App\Helpers\Response;

class SettingsController
{
    protected SettingsRepository $settingsRepo;

    public function __construct(SettingsRepository $settingsRepo)
    {
        $this->settingsRepo = $settingsRepo;
    }

    public function get(): void
    {
        $settings = $this->settingsRepo->getSettings();
        // Include PHP environment upload limits for user diagnostic
        $settings['php_limits'] = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];
        Response::success($settings);
    }

    public function update(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::error('Invalid settings payload.');
            return;
        }

        $allowedKeys = ['app_name', 'theme', 'view_mode', 'sort_by', 'sort_order', 'trash_retention_days', 'storage_quota_mode', 'custom_quota_bytes'];
        $cleanData = [];
        foreach ($allowedKeys as $key) {
            if (isset($input[$key])) {
                $cleanData[$key] = $input[$key];
            }
        }

        $this->settingsRepo->updateSettings($cleanData);
        Response::success($this->settingsRepo->getSettings(), 'Pengaturan berhasil disimpan.');
    }
}
