<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Resource;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceVariant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the stable, non-time-sensitive facts of a booking request
 * (tenant/branch scoping, module boundary, service/branch/employee/resource
 * relationships). Time-sensitive facts (business hours, holidays, employee
 * schedule, existing-appointment conflicts) are deliberately NOT checked
 * here — they're re-verified fresh inside BookAppointment's locked
 * transaction immediately before insert, since they can change between
 * page-load and submit (SKILL.md §16/§17).
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('appointments.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('tenant_id', $tenantId)],
            'service_variant_id' => ['nullable', 'integer', Rule::exists('service_variants', 'id')->where('tenant_id', $tenantId)],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'resource_id' => ['nullable', 'integer', Rule::exists('resources', 'id')->where('tenant_id', $tenantId)],
            'starts_at' => ['required', 'date'],
            'source' => ['nullable', Rule::in(['staff', 'walk_in'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'waitlist_entry_id' => ['nullable', 'integer', Rule::exists('waitlist_entries', 'id')->where('tenant_id', $tenantId)],
            'service_mode' => ['nullable', Rule::in(Appointment::SERVICE_MODES)],
            'delivery_address' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branch = Branch::find($this->input('branch_id'));
            $service = Service::find($this->input('service_id'));
            $employee = User::find($this->input('user_id'));
            $resource = $this->input('resource_id') ? Resource::find($this->input('resource_id')) : null;
            $variant = $this->input('service_variant_id') ? ServiceVariant::find($this->input('service_variant_id')) : null;

            if ($branch && ! $this->user()->canAccessBranch($branch)) {
                $validator->errors()->add('branch_id', 'You do not have access to the selected branch.');
            }

            if ($branch && $service) {
                if (! $branch->hasModuleEnabled($service->module->code)) {
                    $validator->errors()->add('service_id', "This branch does not have the {$service->module->name} module enabled.");
                } elseif (! $service->isAvailableAtBranch($branch)) {
                    $validator->errors()->add('service_id', 'This service is not available at the selected branch.');
                }
            }

            if ($employee && $service && ! $service->capableEmployees()->where('users.id', $employee->id)->exists()) {
                $validator->errors()->add('user_id', 'This staff member is not capable of performing the selected service.');
            }

            if ($resource && $branch && $resource->branch_id !== $branch->id) {
                $validator->errors()->add('resource_id', 'The selected resource does not belong to the selected branch.');
            }

            if ($variant && $service && $variant->service_id !== $service->id) {
                $validator->errors()->add('service_variant_id', 'The selected variant does not belong to the selected service.');
            }

            $serviceMode = $this->input('service_mode') ?: 'branch';

            if ($serviceMode !== 'branch') {
                if ($service && ! $service->isAvailableForMode($serviceMode)) {
                    $validator->errors()->add('service_mode', 'This service is not available as a home or venue booking.');
                }

                if ($this->filled('resource_id')) {
                    $validator->errors()->add('resource_id', 'A room/resource can\'t be selected for a home or venue booking.');
                }

                if (! $this->filled('delivery_address')) {
                    $validator->errors()->add('delivery_address', 'A delivery address is required for a home or venue booking.');
                }
            }
        });
    }
}
