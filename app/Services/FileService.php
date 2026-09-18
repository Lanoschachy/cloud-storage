<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\FileRepository;
use App\Repositories\FolderRepository;
use App\Repositories\TrashRepository;
use App\Repositories\ActivityRepository;
use App\Helpers\Security;
use App\Services\StorageUsageService;

class FileService
{
    protected FileRepository $fileRepo;
    protected FolderRepository $folderRepo;
    protected TrashRepository $trashRepo;
    protected ActivityRepository $activityRepo;
    protected StorageUsageService $storageUsage;
    protected string $storagePath;

    public function __construct(
        FileRepository $fileRepo,
        FolderRepository $folderRepo,
        TrashRepository $trashRepo,
        ActivityRepository $activityRepo,
        StorageUsageService $storageUsage,
        string $storagePath
    ) {
        $this->fileRepo = $fileRepo;
        $this->folderRepo = $folderRepo;
        $this->trashRepo = $trashRepo;
        $this->activityRepo = $activityRepo;
        $this->storageUsage = $storageUsage;
        $this->storagePath = $storagePath;
    }

    public function upload(array $uploadedFile, string $folderId = 'root'): array
    {
        if (!isset($uploadedFile['tmp_name']) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $uploadedFile['error'] ?? 'UNKNOWN';
            return ['success' => false, 'error' => "Gagal mengunggah file (Kode: {$errorCode})."];
        }

        $fileSize = (int)$uploadedFile['size'];
        if (!$this->storageUsage->canAcceptUpload($fileSize)) {
            return ['success' => false, 'error' => 'Kapasitas penyimpanan server tidak mencukupi untuk mengunggah file ini.'];
        }

        // Validate folder
        if ($folderId !== 'root' && !$this->folderRepo->find($folderId)) {
            $folderId = 'root';
        }

        $rawOriginalName = $uploadedFile['name'] ?? 'file_' . time();
        $safeOriginalName = Security::sanitizeFilename($rawOriginalName);
        $ext = strtolower(pathinfo($safeOriginalName, PATHINFO_EXTENSION));

        // Deduplicate filename inside same folder (e.g., photo (1).jpg)
        $finalName = $this->deduplicateFilename($folderId, $safeOriginalName);

        // Generate safe randomized storage filename
        $storedName = Security::generateStoredName($ext);
        $destinationPath = $this->storagePath . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $destinationPath)) {
            return ['success' => false, 'error' => 'Gagal memindahkan file ke direktori penyimpanan.'];
        }

        @chmod($destinationPath, 0640);

        // Server-side MIME detection using finfo
        $realMime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $destinationPath);
                if ($detected) {
                    $realMime = $detected;
                }
                finfo_close($finfo);
            }
        }

        // Extract dimension if image
        $metadata = [];
        if (str_starts_with($realMime, 'image/')) {
            $imgSize = @getimagesize($destinationPath);
            if ($imgSize) {
                $metadata['width'] = $imgSize[0];
                $metadata['height'] = $imgSize[1];
            }
        }

        $record = $this->fileRepo->create([
            'name' => $finalName,
            'stored_name' => $storedName,
            'folder_id' => $folderId,
            'mime_type' => $realMime,
            'extension' => $ext,
            'size' => $fileSize,
            'metadata' => $metadata,
        ]);

        $this->activityRepo->log('upload', 'file', $record['id'], $record['name'], "Size: " . round($fileSize / 1024, 1) . " KB");

        return ['success' => true, 'file' => $record];
    }

    public function renameFile(string $fileId, string $newName): array
    {
        $file = $this->fileRepo->find($fileId);
        if (!$file) {
            return ['success' => false, 'error' => 'File tidak ditemukan.'];
        }

        $cleanName = Security::sanitizeFilename($newName);
        if ($cleanName === '') {
            return ['success' => false, 'error' => 'Nama file tidak boleh kosong.'];
        }

        // Keep extension if user omitted
        $oldExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newExt = pathinfo($cleanName, PATHINFO_EXTENSION);
        if (empty($newExt) && !empty($oldExt)) {
            $cleanName .= '.' . $oldExt;
        }

        $folderId = $file['folder_id'];
        $finalName = $this->deduplicateFilename($folderId, $cleanName, $fileId);

        $oldName = $file['name'];
        $this->fileRepo->update($fileId, ['name' => $finalName]);
        $this->activityRepo->log('rename', 'file', $fileId, $finalName, "Renamed from {$oldName}");

        return ['success' => true, 'name' => $finalName];
    }

    public function moveFile(string $fileId, string $targetFolderId): array
    {
        $file = $this->fileRepo->find($fileId);
        if (!$file) {
            return ['success' => false, 'error' => 'File tidak ditemukan.'];
        }

        if ($targetFolderId !== 'root' && !$this->folderRepo->find($targetFolderId)) {
            return ['success' => false, 'error' => 'Folder tujuan tidak ditemukan.'];
        }

        // Deduplicate in target folder if necessary
        $finalName = $this->deduplicateFilename($targetFolderId, $file['name'], $fileId);

        $this->fileRepo->update($fileId, [
            'folder_id' => $targetFolderId,
            'name' => $finalName,
        ]);

        $this->activityRepo->log('move', 'file', $fileId, $file['name'], "Moved to folder {$targetFolderId}");

        return ['success' => true];
    }

    public function toggleStar(string $fileId): array
    {
        $file = $this->fileRepo->find($fileId);
        if (!$file) {
            return ['success' => false, 'error' => 'File tidak ditemukan.'];
        }

        $isStarred = !($file['is_starred'] ?? false);
        $this->fileRepo->update($fileId, ['is_starred' => $isStarred]);

        $action = $isStarred ? 'star' : 'unstar';
        $this->activityRepo->log($action, 'file', $fileId, $file['name']);

        return ['success' => true, 'is_starred' => $isStarred];
    }

    public function deleteFile(string $fileId): array
    {
        $file = $this->fileRepo->find($fileId);
        if (!$file) {
            return ['success' => false, 'error' => 'File tidak ditemukan.'];
        }

        $this->fileRepo->delete($fileId);
        $this->trashRepo->add('file', $file);
        $this->activityRepo->log('delete', 'file', $fileId, $file['name'], "Moved to trash");

        return ['success' => true];
    }

    public function getPhysicalPath(array $file): ?string
    {
        $storedName = basename($file['stored_name'] ?? '');
        if ($storedName === '') {
            return null;
        }

        $path = $this->storagePath . DIRECTORY_SEPARATOR . $storedName;
        if (!file_exists($path)) {
            return null;
        }

        return $path;
    }

    protected function deduplicateFilename(string $folderId, string $filename, ?string $excludeId = null): string
    {
        $existing = $this->fileRepo->getByFolder($folderId);
        $names = [];
        foreach ($existing as $f) {
            if ($excludeId && $f['id'] === $excludeId) {
                continue;
            }
            $names[strtolower($f['name'])] = true;
        }

        if (!isset($names[strtolower($filename)])) {
            return $filename;
        }

        $info = pathinfo($filename);
        $base = $info['filename'];
        $ext = !empty($info['extension']) ? ('.' . $info['extension']) : '';

        $counter = 1;
        while (isset($names[strtolower("{$base} ({$counter}){$ext}")])) {
            $counter++;
        }

        return "{$base} ({$counter}){$ext}";
    }
}
