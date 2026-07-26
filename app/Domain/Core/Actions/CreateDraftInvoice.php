<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Invoice;

/**
 * `customer_name`/`customer_phone` are snapshotted immediately (not left
 * to be joined live later) so a future DPDP erasure of the customer record
 * can never corrupt this invoice's historical detail (CLAUDE.md §36).
 */
class CreateDraftInvoice
{
    public function execute(Branch $branch, Customer $customer, ?int $createdBy, ?string $notes = null): Invoice
    {
        $invoice = new Invoice([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);
        $invoice->status = 'draft';
        $invoice->save();

        return $invoice;
    }
}
