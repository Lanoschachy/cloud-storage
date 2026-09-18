<?php
declare(strict_types=1);

namespace App\Repositories;

class FolderRepository extends JsonRepository
{
    public function __construct(string $dataPath)
    {
        parent::__construct($dataPath . DIRECTORY_SEPARATOR . 'folders.json');

        // Ensure 'root' exists
        $folders = $this->read();
        if (empty($folders) || !isset($folders['root'])) {
            $folders['root'] = [
                'id' => 'root',
                'name' => 'My Cloud',
                'parent_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $this->write($folders);
        }
    }

    public function all(): array
    {
        return $this->read();
    }

    public function find(string $folderId): ?array
    {
        $folders = $this->read();
        return $folders[$folderId] ?? null;
    }

    public function getByParent(?string $parentId): array
    {
        $folders = $this->read();
        $result = [];
        foreach ($folders as $id => $folder) {
            if ($id === 'root') continue;
            if ($folder['parent_id'] === $parentId) {
                $result[] = $folder;
            }
        }
        return $result;
    }

    public function create(string $name, string $parentId = 'root'): array
    {
        $id = 'folder_' . bin2hex(random_bytes(6));
        $now = date('Y-m-d H:i:s');
        $newFolder = [
            'id' => $id,
            'name' => $name,
            'parent_id' => $parentId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->mutate(function (&$folders) use ($id, $newFolder) {
            $folders[$id] = $newFolder;
            return true;
        });

        return $newFolder;
    }

    public function update(string $id, array $data): bool
    {
        return $this->mutate(function (&$folders) use ($id, $data) {
            if (!isset($folders[$id])) {
                return false;
            }
            $folders[$id] = array_merge($folders[$id], $data, ['updated_at' => date('Y-m-d H:i:s')]);
            return true;
        }) !== false;
    }

    public function delete(string $id): bool
    {
        if ($id === 'root') {
            return false;
        }

        return $this->mutate(function (&$folders) use ($id) {
            if (isset($folders[$id])) {
                unset($folders[$id]);
                return true;
            }
            return false;
        }) !== false;
    }

    /**
     * Get path ancestors for breadcrumbs.
     */
    public function getBreadcrumbs(string $folderId): array
    {
        $folders = $this->read();
        $breadcrumbs = [];
        $currentId = $folderId;

        while ($currentId && isset($folders[$currentId])) {
            $item = $folders[$currentId];
            array_unshift($breadcrumbs, [
                'id' => $item['id'],
                'name' => $item['name'],
            ]);
            $currentId = $item['parent_id'];
        }

        if (empty($breadcrumbs) && isset($folders['root'])) {
            $breadcrumbs[] = ['id' => 'root', 'name' => $folders['root']['name']];
        }

        return $breadcrumbs;
    }
}
