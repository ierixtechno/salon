<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\GiftCard;
use Illuminate\Support\Facades\DB;

/**
 * Writes off the remaining balance to zero via a ledger entry — never
 * deletes the card or its transaction history (CLAUDE.md §48).
 */
class CancelGiftCard
{
    public function execute(GiftCard $giftCard, ?string $reason, ?int $cancelledBy): GiftCard
    {
        abort_unless($giftCard->status === 'active', 409, "Cannot cancel a gift card that is currently {$giftCard->status}.");

        return DB::transaction(function () use ($giftCard, $reason, $cancelledBy) {
            $remaining = (float) $giftCard->balance();

            if ($remaining > 0) {
                $giftCard->transactions()->create([
                    'type' => 'cancel',
                    'amount' => -$remaining,
                    'created_by' => $cancelledBy,
                ]);
            }

            $giftCard->status = 'cancelled';
            $giftCard->cancelled_at = now();
            $giftCard->cancel_reason = $reason;
            $giftCard->save();

            return $giftCard;
        });
    }
}
