<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\DataExport;
use App\Jobs\GenerateTenantDataExport;

class RequestTenantDataExport
{
    public function execute(int $tenantId, ?int $requestedBy): DataExport
    {
        $export = new DataExport(['requested_by' => $requestedBy]);
        $export->tenant_id = $tenantId;
        $export->status = 'pending';
        $export->save();

        GenerateTenantDataExport::dispatch($export->id);

        return $export;
    }
}
