<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\CustomerMembership;
use App\Domain\Core\Models\InvoiceLine;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceVariant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Two mutually-supporting modes: `appointment_id` (auto-fills service/
 * variant/price from a completed Appointment, and can only be used once —
 * an appointment can never be invoiced twice) or a standalone `service_id`
 * (a walk-in sale with no prior booking). Price/tax are never accepted
 * from the client either way — AddInvoiceLine resolves them server-side.
 */
class StoreInvoiceLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'appointment_id' => [
                'nullable', 'integer',
                Rule::exists('appointments', 'id')->where('tenant_id', $tenantId),
            ],
            'service_id' => [
                'required_without:appointment_id', 'nullable', 'integer',
                Rule::exists('services', 'id')->where('tenant_id', $tenantId),
            ],
            'service_variant_id' => [
                'nullable', 'integer',
                Rule::exists('service_variants', 'id')->where('tenant_id', $tenantId),
            ],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'customer_membership_id' => [
                'nullable', 'integer',
                Rule::exists('customer_memberships', 'id')->where('tenant_id', $tenantId),
            ],
            'performed_by' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $invoice = $this->route('invoice');

            if ($this->filled('appointment_id')) {
                $appointment = Appointment::find($this->input('appointment_id'));

                if ($appointment) {
                    if ($appointment->status !== 'completed') {
                        $validator->errors()->add('appointment_id', 'Only completed appointments can be invoiced.');
                    }
                    if ($appointment->customer_id !== $invoice->customer_id) {
                        $validator->errors()->add('appointment_id', 'This appointment belongs to a different customer.');
                    }
                    if (InvoiceLine::where('appointment_id', $appointment->id)->exists()) {
                        $validator->errors()->add('appointment_id', 'This appointment has already been invoiced.');
                    }
                }

                return;
            }

            $service = Service::find($this->input('service_id'));
            $branch = $invoice->branch;

            if ($service && $branch) {
                if (! $branch->hasModuleEnabled($service->module->code)) {
                    $validator->errors()->add('service_id', "This branch does not have the {$service->module->name} module enabled.");
                } elseif (! $service->isAvailableAtBranch($branch)) {
                    $validator->errors()->add('service_id', 'This service is not available at the selected branch.');
                }
            }

            if ($this->filled('service_variant_id')) {
                $variant = ServiceVariant::find($this->input('service_variant_id'));
                if ($variant && $service && $variant->service_id !== $service->id) {
                    $validator->errors()->add('service_variant_id', 'The selected variant does not belong to the selected service.');
                }
            }

            if ($this->filled('performed_by')) {
                $employee = User::find($this->input('performed_by'));
                if ($employee && ! $employee->canAccessBranch($invoice->branch)) {
                    $validator->errors()->add('performed_by', 'This employee does not have access to the invoice\'s branch.');
                }
            }

            if ($this->filled('customer_membership_id')) {
                if ($this->filled('discount_amount') && (float) $this->input('discount_amount') > 0) {
                    $validator->errors()->add('discount_amount', 'A manual discount cannot be combined with a membership discount.');
                }

                $membership = CustomerMembership::find($this->input('customer_membership_id'));
                if ($membership && $membership->customer_id !== $invoice->customer_id) {
                    $validator->errors()->add('customer_membership_id', 'This membership belongs to a different customer.');
                }
            }
        });
    }
}
