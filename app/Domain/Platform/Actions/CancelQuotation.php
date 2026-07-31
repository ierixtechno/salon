<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\Quotation;

class CancelQuotation
{
    public function execute(Quotation $quotation): Quotation
    {
        abort_unless($quotation->status === 'pending', 409, 'Only a pending quotation can be cancelled.');

        $quotation->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return $quotation;
    }
}
