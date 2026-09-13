<?php

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\Quotation;

/**
 * Builds a standard UPI deep link (`upi://pay?...`) for a pending Quotation
 * — any UPI app (GPay, PhonePe, Paytm, a bank's own app, ...) can scan a QR
 * encoding this and pay directly to the business's own UPI ID. No payment
 * gateway involved, so nothing here can fail due to Razorpay being
 * unconfigured — the only precondition is PLATFORM_UPI_VPA being set.
 *
 * Deliberately returns null (never throws) when unconfigured or when the
 * quotation isn't in a payable state — callers (the Blade component) treat
 * that as "don't show a QR", the same graceful-degradation pattern as
 * NullPaymentGatewayProvider/NullWhatsAppProvider.
 */
class BuildUpiPaymentUri
{
    public function for(Quotation $quotation): ?string
    {
        $vpa = config('platform.upi_vpa');

        if (blank($vpa) || $quotation->status !== 'pending') {
            return null;
        }

        $payeeName = (string) config('platform.upi_payee_name', 'StyloBiz');

        // Amount must be a plain decimal string ("2358.82"), not grouped
        // ("2,358.82") — UPI apps reject anything else.
        $amount = number_format((float) $quotation->total_amount, 2, '.', '');

        $params = [
            'pa' => $vpa,
            'pn' => $payeeName,
            'am' => $amount,
            'cu' => 'INR',
            'tn' => "Quotation {$quotation->quotation_number}",
            'tr' => $quotation->quotation_number,
        ];

        return 'upi://pay?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
