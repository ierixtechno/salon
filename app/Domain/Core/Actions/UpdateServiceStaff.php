<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Service;

class UpdateServiceStaff
{
    public function execute(Service $service, array $userIds): void
    {
        $service->capableEmployees()->sync($userIds);
    }
}
