<?php

use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Support\BuildUpiPaymentUri;

test('it returns null when no UPI VPA is configured', function () {
    config(['platform.upi_vpa' => null]);

    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $plan->modules()->sync(Module::whereIn('code', ['salon'])->pluck('id'));
    $quotation = app(CreateQuotation::class)->execute(Tenant::findOrFail($owner->tenant_id), $plan, createdBy: null);

    expect(app(BuildUpiPaymentUri::class)->for($quotation))->toBeNull();
});

test('it builds a correctly-formed upi:// deep link when a VPA is configured', function () {
    config(['platform.upi_vpa' => 'nexbiz@okicici', 'platform.upi_payee_name' => 'NexBiz Technology']);

    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $plan->modules()->sync(Module::whereIn('code', ['salon'])->pluck('id'));
    $quotation = app(CreateQuotation::class)->execute(Tenant::findOrFail($owner->tenant_id), $plan, createdBy: null);

    $uri = app(BuildUpiPaymentUri::class)->for($quotation);

    expect($uri)->not->toBeNull();
    expect($uri)->toStartWith('upi://pay?');

    parse_str(parse_url($uri, PHP_URL_QUERY), $params);
    expect($params['pa'])->toBe('nexbiz@okicici');
    expect($params['pn'])->toBe('NexBiz Technology');
    expect($params['cu'])->toBe('INR');
    expect((float) $params['am'])->toBe((float) $quotation->total_amount);
    expect($params['tr'])->toBe($quotation->quotation_number);
});

test('it returns null for a quotation that is not pending', function () {
    config(['platform.upi_vpa' => 'nexbiz@okicici']);

    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $plan->modules()->sync(Module::whereIn('code', ['salon'])->pluck('id'));
    $quotation = app(CreateQuotation::class)->execute(Tenant::findOrFail($owner->tenant_id), $plan, createdBy: null);
    $quotation->update(['status' => 'cancelled']);

    expect(app(BuildUpiPaymentUri::class)->for($quotation))->toBeNull();
});
