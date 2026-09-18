<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ActivityRepository;
use App\Helpers\Response;

class ActivityController
{
    protected ActivityRepository $activityRepo;

    public function __construct(ActivityRepository $activityRepo)
    {
        $this->activityRepo = $activityRepo;
    }

    public function list(): void
    {
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
        $activities = $this->activityRepo->getRecent($limit);
        Response::success($activities);
    }
}
