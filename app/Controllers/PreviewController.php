<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\FileRepository;
use App\Services\FileService;
use App\Preview\PreviewManager;
use App\Helpers\Response;

class PreviewController
{
    protected FileRepository $fileRepo;
    protected FileService $fileService;

    public function __construct(FileRepository $fileRepo, FileService $fileService)
    {
        $this->fileRepo = $fileRepo;
        $this->fileService = $fileService;
    }

    public function preview(): void
    {
        $fileId = $_GET['id'] ?? '';
        if ($fileId === '') {
            http_response_code(400);
            echo 'File ID required.';
            exit;
        }

        $file = $this->fileRepo->find($fileId);
        if (!$file) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        $physicalPath = $this->fileService->getPhysicalPath($file);
        if (!$physicalPath) {
            http_response_code(404);
            echo 'Physical file missing.';
            exit;
        }

        $this->fileRepo->touchAccess($fileId);
        PreviewManager::serve($file, $physicalPath, false);
    }

    public function download(): void
    {
        $fileId = $_GET['id'] ?? '';
        if ($fileId === '') {
            http_response_code(400);
            echo 'File ID required.';
            exit;
        }

        $file = $this->fileRepo->find($fileId);
        if (!$file) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        $physicalPath = $this->fileService->getPhysicalPath($file);
        if (!$physicalPath) {
            http_response_code(404);
            echo 'Physical file missing.';
            exit;
        }

        PreviewManager::serve($file, $physicalPath, true);
    }

    public function textContent(): void
    {
        $fileId = $_GET['id'] ?? '';
        $file = $this->fileRepo->find($fileId);
        if (!$file) {
            Response::error('File tidak ditemukan.', 404);
            return;
        }

        $physicalPath = $this->fileService->getPhysicalPath($file);
        if (!$physicalPath) {
            Response::error('Physical file missing.', 404);
            return;
        }

        $content = PreviewManager::readTextContent($physicalPath);
        Response::success([
            'id' => $fileId,
            'name' => $file['name'],
            'mime_type' => $file['mime_type'],
            'extension' => $file['extension'],
            'content' => $content,
        ]);
    }
}
