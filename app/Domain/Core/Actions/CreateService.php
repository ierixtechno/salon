<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use Illuminate\Support\Facades\DB;

/**
 * A new service is available at every active branch by default. A service
 * is only sellable (invoice, booking) at branches it is explicitly available
 * at, so leaving this to a separate manual step meant a freshly created
 * service silently never showed up in the sale screen. Restrict it later
 * from the service's Branches tab.
 */
class CreateService
{
    public function execute(array $data, ServiceCategory $category): Service
    {
        return DB::transaction(function () use ($data, $category) {
            $service = Service::create([
                ...$data,
                'module_id' => $category->module_id,
            ]);

            $service->branches()->sync(
                Branch::where('is_active', true)->pluck('id')->mapWithKeys(fn ($id) => [$id => ['is_available' => true]])->all(),
            );

            return $service;
        });
    }
}
