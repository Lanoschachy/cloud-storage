<?php
declare(strict_types=1);

namespace App\Repositories;

class TrashRepository extends JsonRepository
{
    public function __construct(string $dataPath)
    {
        parent::__construct($dataPath . DIRECTORY_SEPARATOR . 'trash.json');
    }

    public function all(): array
    {
        return $this->read();
    }

    public function find(string $trashId): ?array
    {
        $trash = $this->read();
        return $trash[$trashId] ?? null;
    }

    public function add(string $itemType, array $itemData, int $retentionDays = 30): array
    {
        $id = 'trash_' . bin2hex(random_bytes(6));
        $now = time();
        $trashEntry = [
            'id' => $id,
            'item_type' => $itemType, // 'file' or 'folder'
            'original_id' => $itemData['id'],
            'name' => $itemData['name'],
            'data' => $itemData,
            'deleted_at' => date('Y-m-d H:i:s', $now),
            'expires_at' => date('Y-m-d H:i:s', $now + ($retentionDays * 86400)),
        ];

        $this->mutate(function (&$trash) use ($id, $trashEntry) {
            $trash[$id] = $trashEntry;
            return true;
        });

        return $trashEntry;
    }

    public function remove(string $trashId): ?array
    {
        $removed = null;
        $this->mutate(function (&$trash) use ($trashId, &$removed) {
            if (isset($trash[$trashId])) {
                $removed = $trash[$trashId];
                unset($trash[$trashId]);
                return true;
            }
            return false;
        });

        return $removed;
    }

    public function clear(): array
    {
        $all = $this->read();
        $this->write([]);
        return $all;
    }

    public function getExpired(): array
    {
        $trash = $this->read();
        $expired = [];
        $now = date('Y-m-d H:i:s');
        foreach ($trash as $id => $item) {
            if (isset($item['expires_at']) && $item['expires_at'] <= $now) {
                $expired[$id] = $item;
            }
        }
        return $expired;
    }
}
