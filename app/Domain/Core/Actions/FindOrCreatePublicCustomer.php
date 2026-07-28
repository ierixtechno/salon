<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Matches an existing customer by phone within the current (guest-resolved)
 * tenant, or creates a new one. Deliberately never overwrites an existing
 * match's name/email — a public, unauthenticated submitter proving they
 * merely know someone else's phone number must not be able to alter that
 * customer's record. The caller's success response must read identically
 * whichever branch was taken (CLAUDE.md §75: never leak whether a phone/
 * email is already registered).
 */
class FindOrCreatePublicCustomer
{
    public function execute(string $name, string $phone, ?string $email): Customer
    {
        return DB::transaction(function () use ($name, $phone, $email) {
            $customer = Customer::where('phone', $phone)->lockForUpdate()->first();

            if ($customer) {
                return $customer;
            }

            return Customer::create([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'source' => 'online',
            ]);
        });
    }
}
