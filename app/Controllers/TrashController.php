<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\TrashService;
use App\Helpers\Response;

class TrashController
{
    protected TrashService $trashService;

    public function __construct(TrashService $trashService)
    {
        $this->trashService = $trashService;
    }

    public function list(): void
    {
        // Check and clean expired items on each trash visit
        $this->trashService->autoCleanExpired();

        $items = $this->trashService->getTrashItems();
        Response::success($items);
    }

    public function restore(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $trashId = $input['id'] ?? '';

        $result = $this->trashService->restoreItem($trashId);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success([], 'Item berhasil dipulihkan.');
    }

    public function delete(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $trashId = $input['id'] ?? '';

        $result = $this->trashService->permanentlyDelete($trashId);
        if (!$result['success']) {
            Response::error($result['error']);
            return;
        }

        Response::success([], 'Item dihapus secara permanen.');
    }

    public function empty(): void
    {
        $result = $this->trashService->emptyTrash();
        Response::success($result, 'Tempat sampah telah dikosongkan.');
    }
}
