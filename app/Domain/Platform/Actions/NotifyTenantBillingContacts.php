<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Core\Actions\SendNotification;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every billing message a tenant should receive — quotation created,
 * payment received, renewal reminders — goes through here, to every user
 * holding `tenant.billing.manage` (Owner-only by default), as BOTH an
 * in-app notification and an email.
 *
 * Email is not optional decoration: a tenant that has never paid cannot
 * log in at all (LoginRequest), so an in-app notification alone would
 * never reach the one person who most needs to see their first quotation.
 * Renewal reminders likewise must reach owners who haven't opened the app
 * lately. Email failure never affects the billing operation itself — it
 * is queued and recorded on its own notification_logs row (CLAUDE.md
 * §37).
 *
 * tenant()->users() already bypasses TenantScope (see Tenant model) and
 * SendNotification takes an explicit tenantId, so this is safe to call
 * from the platform guard and from the scheduled renewals command alike.
 */
class NotifyTenantBillingContacts
{
    public function quotationCreated(Quotation $quotation): void
    {
        $tenant = $quotation->tenant;
        $plan = $quotation->plan;
        $number = $quotation->quotation_number;
        $total = $this->money($quotation->total_amount);
        $gst = $this->money((float) $quotation->cgst_amount + (float) $quotation->sgst_amount + (float) $quotation->igst_amount);

        $this->eachContact($tenant, function (User $user) use ($tenant, $plan, $quotation, $number, $total, $gst) {
            $howToPay = $tenant->status === 'pending_payment'
                ? "Your account will be activated as soon as your payment is received.\n\n".$this->upiInstructions($quotation)
                : "Log in and open Billing > Quotations to pay online:\n".route('billing.quotations.show', $quotation)."\n\n".$this->upiInstructions($quotation, optional: true);

            $this->deliver(
                $tenant, $user,
                inAppSubject: 'Billing update',
                inAppBody: "A new quotation ({$number}) is awaiting your review.",
                emailSubject: "Quotation {$number} - ₹{$total}",
                emailBody: "Hello {$user->name},\n\n"
                    ."A quotation has been prepared for {$tenant->name}.\n\n"
                    ."Quotation: {$number}\n"
                    ."Plan: {$plan->name}\n"
                    ."GST: ₹{$gst}\n"
                    ."Total payable: ₹{$total}\n\n"
                    .$howToPay
                    .$this->signOff(),
                referenceType: 'Quotation',
                referenceId: $quotation->id,
            );
        });
    }

    public function paymentReceived(Quotation $quotation, PlatformInvoice $invoice, Carbon $endsAt, bool $firstActivation): void
    {
        $tenant = $quotation->tenant;
        $amount = $this->money($invoice->amount);
        $planName = $quotation->plan->name;

        $this->eachContact($tenant, function (User $user) use ($tenant, $invoice, $endsAt, $firstActivation, $amount, $planName) {
            $next = $firstActivation
                ? "Your account is now active. You can log in here:\n".route('login')
                : "You can view your invoice under Billing > Invoices:\n".route('billing.invoices.index');

            $this->deliver(
                $tenant, $user,
                inAppSubject: 'Payment received',
                inAppBody: "Your payment was received. Invoice {$invoice->invoice_number} is available.",
                emailSubject: "Payment received - Invoice {$invoice->invoice_number}",
                emailBody: "Hello {$user->name},\n\n"
                    ."Thank you - we've received ₹{$amount} for the {$planName} plan.\n\n"
                    ."Invoice: {$invoice->invoice_number}\n"
                    .'Active until: '.$endsAt->format('d M Y')."\n\n"
                    .$next
                    .$this->signOff(),
                referenceType: 'PlatformInvoice',
                referenceId: $invoice->id,
            );
        });
    }

    /**
     * `$message` is the tail of the sentence "Your {plan} subscription ..."
     * exactly as ProcessSubscriptionRenewals words it for the in-app copy.
     */
    public function renewalReminder(TenantSubscription $subscription, string $message): void
    {
        $tenant = $subscription->tenant;
        $planName = $subscription->plan->name;

        $this->eachContact($tenant, function (User $user) use ($tenant, $subscription, $planName, $message) {
            $this->deliver(
                $tenant, $user,
                inAppSubject: 'Subscription renewal',
                inAppBody: "Your {$planName} subscription {$message}",
                emailSubject: "Subscription renewal - {$planName}",
                emailBody: "Hello {$user->name},\n\n"
                    ."Your {$planName} subscription {$message}\n\n"
                    ."Log in and open Billing to renew:\n".route('billing.quotations.index')
                    .$this->signOff(),
                referenceType: 'TenantSubscription',
                referenceId: $subscription->id,
            );
        });
    }

    private function eachContact(Tenant $tenant, callable $callback): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $tenant->users()
            ->get()
            ->filter(fn (User $user) => $user->can('tenant.billing.manage'))
            ->each($callback);
    }

    private function deliver(
        Tenant $tenant,
        User $user,
        string $inAppSubject,
        string $inAppBody,
        string $emailSubject,
        string $emailBody,
        string $referenceType,
        int $referenceId,
    ): void {
        $send = app(SendNotification::class);

        $send->execute(
            tenantId: $tenant->id,
            channel: 'in_app',
            recipientType: 'user',
            recipientId: $user->id,
            toAddress: null,
            subject: $inAppSubject,
            body: $inAppBody,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );

        $send->execute(
            tenantId: $tenant->id,
            channel: 'email',
            recipientType: 'user',
            recipientId: $user->id,
            toAddress: $user->email,
            subject: $emailSubject,
            body: $emailBody,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );
    }

    /**
     * How to actually pay by UPI, when a VPA is configured (see
     * BuildUpiPaymentUri) — an email can't carry a scannable QR reliably,
     * so the same details are spelled out as text instead.
     */
    private function upiInstructions(Quotation $quotation, bool $optional = false): string
    {
        $vpa = config('platform.upi_vpa');

        if (blank($vpa)) {
            return $optional
                ? ''
                : "To arrange payment, please contact us quoting {$quotation->quotation_number}.";
        }

        $lead = $optional ? 'Or pay by UPI' : 'Pay by UPI';

        return "{$lead} to {$vpa} (".config('platform.upi_payee_name').'), '
            .'amount ₹'.$this->money($quotation->total_amount).', '
            ."reference {$quotation->quotation_number}.";
    }

    private function signOff(): string
    {
        $support = config('platform.support_email');

        return "\n\n".($support ? "Questions? Write to {$support}.\n\n" : '').'- '.config('platform.brand_name');
    }

    private function money(float|string $amount): string
    {
        return number_format((float) $amount, 2);
    }
}
