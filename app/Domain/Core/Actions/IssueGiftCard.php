<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\GiftCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `code` is server-generated (CLAUDE.md §19 — internal keys aren't exposed,
 * and here even the public reference is never client-supplied) and
 * verified unique per tenant before insert. Purchase is recorded directly
 * — see docs/decisions/README.md D-006 (same scope note as
 * SellPackageToCustomer/SellMembershipToCustomer).
 */
class IssueGiftCard
{
    public function execute(
        float $initialValue,
        ?Customer $customer,
        string $purchaseMethod,
        ?string $purchaseReference,
        ?\DateTimeInterface $expiresAt,
        ?int $issuedBy,
    ): GiftCard {
        abort_if($initialValue <= 0, 422, 'Gift card value must be greater than zero.');

        return DB::transaction(function () use ($initialValue, $customer, $purchaseMethod, $purchaseReference, $expiresAt, $issuedBy) {
            do {
                $code = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
            } while (GiftCard::where('code', $code)->exists());

            $giftCard = new GiftCard([
                'code' => $code,
                'initial_value' => $initialValue,
                'customer_id' => $customer?->id,
                'purchase_method' => $purchaseMethod,
                'purchase_reference' => $purchaseReference,
                'expires_at' => $expiresAt,
                'issued_by' => $issuedBy,
                'issued_at' => now(),
            ]);
            $giftCard->status = 'active';
            $giftCard->save();

            $giftCard->transactions()->create([
                'type' => 'issue',
                'amount' => $initialValue,
                'created_by' => $issuedBy,
            ]);

            return $giftCard;
        });
    }
}
