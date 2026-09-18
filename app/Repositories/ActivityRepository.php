<?php
declare(strict_types=1);

namespace App\Repositories;

class ActivityRepository extends JsonRepository
{
    protected int $maxEntries;

    public function __construct(string $dataPath, int $maxEntries = 200)
    {
        parent::__construct($dataPath . DIRECTORY_SEPARATOR . 'activity.json');
        $this->maxEntries = $maxEntries;
    }

    public function log(string $action, string $itemType, string $itemId, string $itemName, string $details = ''): array
    {
        $entry = [
            'id' => 'act_' . bin2hex(random_bytes(6)),
            'action' => $action,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'item_name' => $itemName,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->mutate(function (&$activities) use ($entry) {
            if (!is_array($activities)) {
                $activities = [];
            }
            array_unshift($activities, $entry);
            if (count($activities) > $this->maxEntries) {
                $activities = array_slice($activities, 0, $this->maxEntries);
            }
            return true;
        });

        return $entry;
    }

    public function getRecent(int $limit = 50): array
    {
        $activities = $this->read();
        return array_slice($activities, 0, $limit);
    }
}
