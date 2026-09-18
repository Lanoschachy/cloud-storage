<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\TrashRepository;
use App\Repositories\FileRepository;
use App\Repositories\FolderRepository;
use App\Repositories\ActivityRepository;

class TrashService
{
    protected TrashRepository $trashRepo;
    protected FileRepository $fileRepo;
    protected FolderRepository $folderRepo;
    protected ActivityRepository $activityRepo;
    protected string $storagePath;

    public function __construct(
        TrashRepository $trashRepo,
        FileRepository $fileRepo,
        FolderRepository $folderRepo,
        ActivityRepository $activityRepo,
        string $storagePath
    ) {
        $this->trashRepo = $trashRepo;
        $this->fileRepo = $fileRepo;
        $this->folderRepo = $folderRepo;
        $this->activityRepo = $activityRepo;
        $this->storagePath = $storagePath;
    }

    public function getTrashItems(): array
    {
        return array_values($this->trashRepo->all());
    }

    public function restoreItem(string $trashId): array
    {
        $item = $this->trashRepo->find($trashId);
        if (!$item) {
            return ['success' => false, 'error' => 'Item tidak ditemukan di tempat sampah.'];
        }

        $type = $item['item_type'];
        $data = $item['data'];

        if ($type === 'file') {
            // Check if folder exists, if not, move to root
            $folderId = $data['folder_id'] ?? 'root';
            if ($folderId !== 'root' && !$this->folderRepo->find($folderId)) {
                $data['folder_id'] = 'root';
            }
            $this->fileRepo->create($data);
            $this->trashRepo->remove($trashId);
            $this->activityRepo->log('restore', 'file', $data['id'], $data['name'], 'Restored from trash');
        } elseif ($type === 'folder') {
            $parentId = $data['parent_id'] ?? 'root';
            if ($parentId !== 'root' && !$this->folderRepo->find($parentId)) {
                $data['parent_id'] = 'root';
            }
            $this->folderRepo->update($data['id'], $data);
            $this->trashRepo->remove($trashId);
            $this->activityRepo->log('restore', 'folder', $data['id'], $data['name'], 'Restored from trash');
        }

        return ['success' => true];
    }

    public function permanentlyDelete(string $trashId): array
    {
        $item = $this->trashRepo->find($trashId);
        if (!$item) {
            return ['success' => false, 'error' => 'Item tidak ditemukan di tempat sampah.'];
        }

        if ($item['item_type'] === 'file') {
            $storedName = basename($item['data']['stored_name'] ?? '');
            if ($storedName !== '') {
                $filePhysicalPath = $this->storagePath . DIRECTORY_SEPARATOR . $storedName;
                if (file_exists($filePhysicalPath)) {
                    @unlink($filePhysicalPath);
                }
            }
        }

        $this->trashRepo->remove($trashId);
        $this->activityRepo->log('permanent_delete', $item['item_type'], $item['original_id'], $item['name'], 'Permanently deleted');

        return ['success' => true];
    }

    public function emptyTrash(): array
    {
        $items = $this->trashRepo->clear();
        $count = 0;
        foreach ($items as $item) {
            if ($item['item_type'] === 'file') {
                $storedName = basename($item['data']['stored_name'] ?? '');
                if ($storedName !== '') {
                    $filePhysicalPath = $this->storagePath . DIRECTORY_SEPARATOR . $storedName;
                    if (file_exists($filePhysicalPath)) {
                        @unlink($filePhysicalPath);
                    }
                }
            }
            $count++;
        }

        $this->activityRepo->log('empty_trash', 'system', 'trash', 'All Trash Items', "Purged {$count} items");

        return ['success' => true, 'count' => $count];
    }

    public function autoCleanExpired(): int
    {
        $expired = $this->trashRepo->getExpired();
        $cleaned = 0;
        foreach ($expired as $id => $item) {
            $this->permanentlyDelete($id);
            $cleaned++;
        }
        return $cleaned;
    }
}
