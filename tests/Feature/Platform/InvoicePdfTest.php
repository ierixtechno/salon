<?php

use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;

function pdfTestPaidInvoice($owner): PlatformInvoice
{
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute($owner->tenant, $plan, createdBy: null);

    return app(PayQuotation::class)->execute($quotation, 'upi', 'UTR12345');
}

test('a tenant can download their invoice as a PDF', function () {
    $owner = onboard();
    $invoice = pdfTestPaidInvoice($owner);

    $response = $this->actingAs($owner)->get(route('billing.invoices.pdf', $invoice));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain(str_replace('/', '-', $invoice->invoice_number).'.pdf');
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

test('super admin can download any invoice as a PDF', function () {
    $owner = onboard();
    $invoice = pdfTestPaidInvoice($owner);
    $admin = PlatformAdmin::factory()->create();

    $response = $this->actingAs($admin, 'platform')->get(route('platform.invoices.pdf', $invoice));

    $response->assertOk();
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

test('both invoice pages offer the PDF download', function () {
    $owner = onboard();
    $invoice = pdfTestPaidInvoice($owner);
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($owner)->get(route('billing.invoices.show', $invoice))
        ->assertOk()->assertSee(route('billing.invoices.pdf', $invoice), false);

    $this->actingAs($admin, 'platform')->get(route('platform.invoices.show', $invoice))
        ->assertOk()->assertSee(route('platform.invoices.pdf', $invoice), false);
});

test('a tenant cannot download another tenant\'s invoice PDF', function () {
    $ownerA = onboard();
    $ownerB = onboard();
    $invoiceA = pdfTestPaidInvoice($ownerA);

    $this->actingAs($ownerB)->get(route('billing.invoices.pdf', $invoiceA))->assertNotFound();
});

test('a guest cannot download an invoice PDF', function () {
    $owner = onboard();
    $invoice = pdfTestPaidInvoice($owner);

    $this->get(route('billing.invoices.pdf', $invoice))->assertRedirect();
    $this->get(route('platform.invoices.pdf', $invoice))->assertRedirect();
});

// ---------------------------------------------- paid quotations are removed

test('once a quotation is paid it no longer exists, and the invoice keeps its number', function () {
    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute($owner->tenant, $plan, createdBy: null);
    $number = $quotation->quotation_number;

    $invoice = app(PayQuotation::class)->execute($quotation, 'upi', 'UTR1');

    expect(Quotation::find($quotation->id))->toBeNull();
    expect(Quotation::where('quotation_number', $number)->exists())->toBeFalse();
    expect($invoice->fresh()->quotation_number)->toBe($number);
    expect($invoice->fresh()->quotation_id)->toBeNull();

    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($admin, 'platform')->get('/platform/quotations')->assertOk()->assertDontSee($number);
    $this->actingAs($admin, 'platform')->get(route('platform.invoices.show', $invoice))->assertOk()->assertSee($number);
});

test('a second payment for the same (now deleted) quotation is rejected', function () {
    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute($owner->tenant, $plan, createdBy: null);
    $stale = Quotation::findOrFail($quotation->id); // what a racing request would still hold

    app(PayQuotation::class)->execute($quotation, 'upi', 'UTR1');

    expect(fn () => app(PayQuotation::class)->execute($stale, 'razorpay', 'pay_dup'))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect(PlatformInvoice::where('tenant_id', $owner->tenant_id)->count())->toBe(1);
});

test('the tenant\'s quotations list no longer shows a quotation after it is paid', function () {
    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute($owner->tenant, $plan, createdBy: null);

    $this->actingAs($owner)->get('/billing/quotations')->assertOk()->assertSee($quotation->quotation_number);

    app(PayQuotation::class)->execute($quotation, 'upi', 'UTR1');

    $this->actingAs($owner)->get('/billing/quotations')->assertOk()->assertDontSee($quotation->quotation_number);
    $this->actingAs($owner)->get(route('billing.quotations.show', $quotation->id))->assertNotFound();
});
