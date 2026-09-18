<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\FileRepository;
use App\Repositories\FolderRepository;
use App\Services\FileService;
use App\Services\FolderService;
use App\Services\StorageUsageService;
use App\Preview\PreviewManager;
use App\Helpers\Response;

class FileController
{
    protected FileRepository $fileRepo;
    protected FolderRepository $folderRepo;
    protected FileService $fileService;
    protected FolderService $folderService;
    protected StorageUsageService $storageUsage;

    public function __construct(
        FileRepository $fileRepo,
        FolderRepository $folderRepo,
        FileService $fileService,
        FolderService $folderService,
        StorageUsageService $storageUsage
    ) {
        $this->fileRepo = $fileRepo;
        $this->folderRepo = $folderRepo;
        $this->fileService = $fileService;
        $this->folderService = $folderService;
        $this->storageUsage = $storageUsage;
    }

    public function list(): void
    {
        $folderId = $_GET['folder_id'] ?? 'root';
        $viewType = $_GET['view'] ?? 'files'; // 'files', 'starred', 'recent'

        $breadcrumbs = [];
        $folders = [];
        $files = [];

        if ($viewType === 'starred') {
            $files = $this->fileRepo->getStarred();
        } elseif ($viewType === 'recent') {
            $files = $this->fileRepo->getRecent(30);
        } else {
            // Normal directory browsing
            $targetFolder = $this->folderRepo->find($folderId);
            if (!$targetFolder) {
                $folderId = 'root';
            }
            $breadcrumbs = $this->folderRepo->getBreadcrumbs($folderId);
            $folders = $this->folderRepo->getByParent($folderId);
            $files = $this->fileRepo->getByFolder($folderId);
        }

        // Augment files with preview category
        foreach ($files as &$f) {
            $f['preview_type'] = PreviewManager::getPreviewType($f['mime_type'] ?? '', $f['extension'] ?? '');
        }

        Response::success([
            'current_folder_id' => $folderId,
            'breadcrumbs' => $breadcrumbs,
            'folders' => array_values($folders),
            'files' => array_values($files),
            'storage' => $this->storageUsage->getUsage(),
        ]);
    }

    public function upload(): void
    {
        if (empty($_FILES['file'])) {
            Response::error('Tidak ada file yang diunggah.');
            return;
        }

        $folderId = $_POST['folder_id'] ?? 'root';
        $result = $this->fileService->upload($_FILES['file'], $folderId);

        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        $file = $result['file'];
        $file['preview_type'] = PreviewManager::getPreviewType($file['mime_type'] ?? '', $file['extension'] ?? '');

        Response::success($file, 'File berhasil diunggah.', 201);
    }

    public function rename(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $fileId = $input['id'] ?? '';
        $newName = $input['name'] ?? '';

        $result = $this->fileService->renameFile($fileId, $newName);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success(['name' => $result['name']], 'File berhasil diubah namanya.');
    }

    public function move(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $fileId = $input['id'] ?? '';
        $targetFolderId = $input['target_folder_id'] ?? 'root';

        $result = $this->fileService->moveFile($fileId, $targetFolderId);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success([], 'File berhasil dipindahkan.');
    }

    public function toggleStar(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $fileId = $input['id'] ?? '';

        $result = $this->fileService->toggleStar($fileId);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success(['is_starred' => $result['is_starred']], 'Status bintang diperbarui.');
    }

    public function delete(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $fileId = $input['id'] ?? '';

        $result = $this->fileService->deleteFile($fileId);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success([], 'File dipindahkan ke tempat sampah.');
    }

    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        if ($query === '') {
            Response::success(['files' => [], 'folders' => []]);
            return;
        }

        $allFiles = $this->fileRepo->all();
        $allFolders = $this->folderRepo->all();

        $matchedFiles = [];
        foreach ($allFiles as $file) {
            if (stripos($file['name'], $query) !== false || stripos($file['extension'] ?? '', $query) !== false) {
                $file['preview_type'] = PreviewManager::getPreviewType($file['mime_type'] ?? '', $file['extension'] ?? '');
                $matchedFiles[] = $file;
            }
        }

        $matchedFolders = [];
        foreach ($allFolders as $folder) {
            if ($folder['id'] === 'root') continue;
            if (stripos($folder['name'], $query) !== false) {
                $matchedFolders[] = $folder;
            }
        }

        Response::success([
            'files' => $matchedFiles,
            'folders' => $matchedFolders,
        ]);
    }
}
