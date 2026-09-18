<?php
declare(strict_types=1);

namespace App\Repositories;

class FileRepository extends JsonRepository
{
    public function __construct(string $dataPath)
    {
        parent::__construct($dataPath . DIRECTORY_SEPARATOR . 'files.json');
    }

    public function all(): array
    {
        return $this->read();
    }

    public function find(string $fileId): ?array
    {
        $files = $this->read();
        return $files[$fileId] ?? null;
    }

    public function getByFolder(string $folderId): array
    {
        $files = $this->read();
        $result = [];
        foreach ($files as $file) {
            if ($file['folder_id'] === $folderId) {
                $result[] = $file;
            }
        }
        return $result;
    }

    public function getStarred(): array
    {
        $files = $this->read();
        $result = [];
        foreach ($files as $file) {
            if (!empty($file['is_starred'])) {
                $result[] = $file;
            }
        }
        return $result;
    }

    public function getRecent(int $limit = 20): array
    {
        $files = $this->read();
        $list = array_values($files);
        usort($list, function ($a, $b) {
            $timeA = strtotime($a['last_accessed_at'] ?? $a['uploaded_at']);
            $timeB = strtotime($b['last_accessed_at'] ?? $b['uploaded_at']);
            return $timeB <=> $timeA;
        });
        return array_slice($list, 0, $limit);
    }

    public function create(array $fileData): array
    {
        $id = $fileData['id'] ?? ('file_' . bin2hex(random_bytes(6)));
        $fileData['id'] = $id;
        $now = date('Y-m-d H:i:s');
        $fileData['uploaded_at'] = $fileData['uploaded_at'] ?? $now;
        $fileData['updated_at'] = $now;
        $fileData['last_accessed_at'] = $now;
        $fileData['is_starred'] = $fileData['is_starred'] ?? false;

        $this->mutate(function (&$files) use ($id, $fileData) {
            $files[$id] = $fileData;
            return true;
        });

        return $fileData;
    }

    public function update(string $id, array $data): bool
    {
        return $this->mutate(function (&$files) use ($id, $data) {
            if (!isset($files[$id])) {
                return false;
            }
            $files[$id] = array_merge($files[$id], $data, ['updated_at' => date('Y-m-d H:i:s')]);
            return true;
        }) !== false;
    }

    public function delete(string $id): ?array
    {
        $deletedItem = null;
        $this->mutate(function (&$files) use ($id, &$deletedItem) {
            if (isset($files[$id])) {
                $deletedItem = $files[$id];
                unset($files[$id]);
                return true;
            }
            return false;
        });

        return $deletedItem;
    }

    public function touchAccess(string $id): void
    {
        $this->mutate(function (&$files) use ($id) {
            if (isset($files[$id])) {
                $files[$id]['last_accessed_at'] = date('Y-m-d H:i:s');
                return true;
            }
            return false;
        });
    }

    public function getTotalSize(): int
    {
        $files = $this->read();
        $total = 0;
        foreach ($files as $file) {
            $total += (int)($file['size'] ?? 0);
        }
        return $total;
    }
}
