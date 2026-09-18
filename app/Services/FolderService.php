<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\FolderRepository;
use App\Repositories\FileRepository;
use App\Repositories\TrashRepository;
use App\Repositories\ActivityRepository;
use App\Helpers\Security;

class FolderService
{
    protected FolderRepository $folderRepo;
    protected FileRepository $fileRepo;
    protected TrashRepository $trashRepo;
    protected ActivityRepository $activityRepo;

    public function __construct(
        FolderRepository $folderRepo,
        FileRepository $fileRepo,
        TrashRepository $trashRepo,
        ActivityRepository $activityRepo
    ) {
        $this->folderRepo = $folderRepo;
        $this->fileRepo = $fileRepo;
        $this->trashRepo = $trashRepo;
        $this->activityRepo = $activityRepo;
    }

    public function createFolder(string $name, string $parentId = 'root'): array
    {
        $name = trim(Security::sanitizeFilename($name));
        if ($name === '') {
            return ['success' => false, 'error' => 'Nama folder tidak boleh kosong.'];
        }

        // Validate parent exists
        $parent = $this->folderRepo->find($parentId);
        if (!$parent) {
            $parentId = 'root';
        }

        // Check duplicate name inside same parent
        $existing = $this->folderRepo->getByParent($parentId);
        foreach ($existing as $f) {
            if (strcasecmp($f['name'], $name) === 0) {
                return ['success' => false, 'error' => 'Folder dengan nama tersebut sudah ada di lokasi ini.'];
            }
        }

        $folder = $this->folderRepo->create($name, $parentId);
        $this->activityRepo->log('create_folder', 'folder', $folder['id'], $folder['name'], "Created folder in {$parentId}");

        return ['success' => true, 'folder' => $folder];
    }

    public function renameFolder(string $folderId, string $newName): array
    {
        if ($folderId === 'root') {
            return ['success' => false, 'error' => 'Root folder tidak dapat diubah namanya.'];
        }

        $folder = $this->folderRepo->find($folderId);
        if (!$folder) {
            return ['success' => false, 'error' => 'Folder tidak ditemukan.'];
        }

        $newName = trim(Security::sanitizeFilename($newName));
        if ($newName === '') {
            return ['success' => false, 'error' => 'Nama folder tidak boleh kosong.'];
        }

        $parentId = $folder['parent_id'];
        $existing = $this->folderRepo->getByParent($parentId);
        foreach ($existing as $f) {
            if ($f['id'] !== $folderId && strcasecmp($f['name'], $newName) === 0) {
                return ['success' => false, 'error' => 'Folder dengan nama tersebut sudah ada di lokasi ini.'];
            }
        }

        $oldName = $folder['name'];
        $this->folderRepo->update($folderId, ['name' => $newName]);
        $this->activityRepo->log('rename_folder', 'folder', $folderId, $newName, "Renamed from {$oldName}");

        return ['success' => true];
    }

    public function moveFolder(string $folderId, string $targetParentId): array
    {
        if ($folderId === 'root') {
            return ['success' => false, 'error' => 'Root folder tidak dapat dipindahkan.'];
        }

        if ($folderId === $targetParentId) {
            return ['success' => false, 'error' => 'Folder tidak dapat dipindahkan ke dirinya sendiri.'];
        }

        $folder = $this->folderRepo->find($folderId);
        if (!$folder) {
            return ['success' => false, 'error' => 'Folder tidak ditemukan.'];
        }

        $targetParent = $this->folderRepo->find($targetParentId);
        if (!$targetParent) {
            return ['success' => false, 'error' => 'Folder tujuan tidak ditemukan.'];
        }

        // Prevent cycle: target cannot be a child or descendant of the folder being moved
        if ($this->isDescendant($folderId, $targetParentId)) {
            return ['success' => false, 'error' => 'Folder tidak dapat dipindahkan ke dalam subfoldernya sendiri.'];
        }

        $this->folderRepo->update($folderId, ['parent_id' => $targetParentId]);
        $this->activityRepo->log('move_folder', 'folder', $folderId, $folder['name'], "Moved to {$targetParent['name']}");

        return ['success' => true];
    }

    public function deleteFolder(string $folderId): array
    {
        if ($folderId === 'root') {
            return ['success' => false, 'error' => 'Root folder tidak dapat dihapus.'];
        }

        $folder = $this->folderRepo->find($folderId);
        if (!$folder) {
            return ['success' => false, 'error' => 'Folder tidak ditemukan.'];
        }

        // Soft delete all child files and folders recursively
        $this->softDeleteSubtree($folderId);

        // Soft delete this folder
        $this->folderRepo->delete($folderId);
        $this->trashRepo->add('folder', $folder);
        $this->activityRepo->log('delete_folder', 'folder', $folderId, $folder['name'], "Moved to trash");

        return ['success' => true];
    }

    protected function isDescendant(string $ancestorId, string $checkId): bool
    {
        $currentId = $checkId;
        $all = $this->folderRepo->all();

        while ($currentId && isset($all[$currentId])) {
            if ($all[$currentId]['parent_id'] === $ancestorId) {
                return true;
            }
            $currentId = $all[$currentId]['parent_id'];
        }

        return false;
    }

    protected function softDeleteSubtree(string $parentId): void
    {
        // Delete all files in this folder
        $files = $this->fileRepo->getByFolder($parentId);
        foreach ($files as $file) {
            $this->fileRepo->delete($file['id']);
            $this->trashRepo->add('file', $file);
        }

        // Delete all child folders recursively
        $children = $this->folderRepo->getByParent($parentId);
        foreach ($children as $child) {
            $this->softDeleteSubtree($child['id']);
            $this->folderRepo->delete($child['id']);
            $this->trashRepo->add('folder', $child);
        }
    }
}
