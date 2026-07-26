<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Service;
use Illuminate\Support\Facades\DB;

class UpdateServiceVariants
{
    public function execute(Service $service, array $variants): void
    {
        DB::transaction(function () use ($service, $variants) {
            $keepIds = [];

            foreach ($variants as $row) {
                $variant = $service->variants()->updateOrCreate(
                    ['id' => $row['id'] ?? null],
                    [
                        'name' => $row['name'],
                        'price' => $row['price'] ?? null,
                        'duration_minutes' => $row['duration_minutes'] ?? null,
                    ],
                );

                $keepIds[] = $variant->id;
            }

            $service->variants()->whereNotIn('id', $keepIds)->delete();
        });
    }
}
