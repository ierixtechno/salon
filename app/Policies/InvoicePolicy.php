<?php

namespace App\Policies;

use App\Domain\Core\Models\Invoice;
use App\Models\User;

/**
 * `update` here means "still building/finalizing the draft" (add/remove
 * lines, checkout) — gated by `invoices.create` since managing a draft is
 * part of creating the sale, not a separate editing capability. `void` is
 * its own permission (`invoices.void`) — CLAUDE.md/docs/04-RBAC.md
 * deliberately keeps Manager out of billing-reversal actions by default.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view')
            && $user->tenant_id === $invoice->tenant_id
            && $user->canAccessBranch($invoice->branch);
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.create')
            && $user->tenant_id === $invoice->tenant_id
            && $user->canAccessBranch($invoice->branch);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.void')
            && $user->tenant_id === $invoice->tenant_id
            && $user->canAccessBranch($invoice->branch);
    }
}
