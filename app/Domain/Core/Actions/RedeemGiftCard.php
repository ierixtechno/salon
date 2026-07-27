<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\GiftCard;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Concurrency: the GiftCard row is locked before the balance check so two
 * simultaneous redemptions (e.g. the same card used at two POS terminals)
 * can never both spend the same balance (same "lock a proxy row" pattern
 * as RecordStockMovement, Phase 7).
 */
class RedeemGiftCard
{
    public function execute(Invoice $invoice, string $code, float $amount, string $idempotencyKey, ?int $redeemedBy): Payment
    {
        abort_if($amount <= 0, 422, 'Redemption amount must be greater than zero.');

        $giftCard = GiftCard::where('code', strtoupper(trim($code)))->first();
        abort_if(! $giftCard, 404, 'No gift card found with that code.');

        return DB::transaction(function () use ($invoice, $giftCard, $amount, $idempotencyKey, $redeemedBy) {
            $giftCard = GiftCard::whereKey($giftCard->id)->lockForUpdate()->firstOrFail();

            abort_unless($giftCard->isUsable(), 409, 'This gift card is not active or has expired.');

            $balance = (float) $giftCard->balance();
            abort_if($amount > $balance, 409, "Redemption would exceed the gift card's available balance of {$balance}.");

            $payment = app(RecordPayment::class)->execute(
                invoice: $invoice,
                method: 'gift_card',
                amount: $amount,
                idempotencyKey: $idempotencyKey,
                recordedBy: $redeemedBy,
                giftCardId: $giftCard->id,
            );

            if (! $giftCard->transactions()->where('reference_type', 'payment')->where('reference_id', $payment->id)->exists()) {
                $giftCard->transactions()->create([
                    'type' => 'redeem',
                    'amount' => -$amount,
                    'reference_type' => 'payment',
                    'reference_id' => $payment->id,
                    'created_by' => $redeemedBy,
                ]);
            }

            return $payment;
        });
    }
}
