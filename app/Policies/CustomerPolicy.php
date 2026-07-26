<?php

namespace App\Policies;

use App\Domain\Core\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view') && $user->tenant_id === $customer->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    /**
     * Deliberately does NOT check isErased() here — authorizeResource()
     * maps both the GET edit route and the PUT update route to this same
     * ability, so blocking here would make an erased customer's page
     * (which is supposed to render a read-only "erased" notice)
     * inaccessible entirely. "Can't actually save changes to an erased
     * customer" is a business-rule conflict enforced in the controller's
     * update() method, not an authorization concern.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update') && $user->tenant_id === $customer->tenant_id;
    }

    public function deactivate(User $user, Customer $customer): bool
    {
        return $user->can('customers.deactivate') && $user->tenant_id === $customer->tenant_id;
    }

    /**
     * authorizeResource() checks the 'delete' ability for the resourceful
     * destroy() route regardless of what destroy() actually does — alias
     * it to the same check as deactivate() since that's all destroy()
     * performs (see CustomerController::destroy()).
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $this->deactivate($user, $customer);
    }

    /**
     * Deliberately a separate, narrower ability from update/delete — DPDP
     * erasure is a distinct, higher-stakes action (CLAUDE.md §36) gated
     * behind its own permission (Owner only by default).
     */
    public function erase(User $user, Customer $customer): bool
    {
        return $user->can('customers.erase') && $user->tenant_id === $customer->tenant_id;
    }
}
