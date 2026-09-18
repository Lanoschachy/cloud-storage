<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\FolderRepository;
use App\Services\FolderService;
use App\Helpers\Response;

class FolderController
{
    protected FolderRepository $folderRepo;
    protected FolderService $folderService;

    public function __construct(FolderRepository $folderRepo, FolderService $folderService)
    {
        $this->folderRepo = $folderRepo;
        $this->folderService = $folderService;
    }

    public function create(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        $parentId = $input['parent_id'] ?? 'root';

        $result = $this->folderService->createFolder($name, $parentId);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success($result['folder'], 'Folder berhasil dibuat.', 201);
    }

    public function rename(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? '';
        $name = $input['name'] ?? '';

        $result = $this->folderService->renameFolder($id, $name);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success([], 'Folder berhasil diubah namanya.');
    }

    public function move(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? '';
        $targetParentId = $input['target_parent_id'] ?? 'root';

        $result = $this->folderService->moveFolder($id, $targetParentId);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success([], 'Folder berhasil dipindahkan.');
    }

    public function delete(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? '';

        $result = $this->folderService->deleteFolder($id);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success([], 'Folder dipindahkan ke tempat sampah.');
    }

    public function tree(): void
    {
        $all = $this->folderRepo->all();
        $tree = [];
        foreach ($all as $folder) {
            $tree[] = [
                'id' => $folder['id'],
                'name' => $folder['name'],
                'parent_id' => $folder['parent_id'],
            ];
        }
        Response::success($tree);
    }
}
