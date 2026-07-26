<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceBranchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('services.update');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branches' => ['present', 'array'],
            'branches.*.branch_id' => [
                'required', 'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId),
            ],
            'branches.*.is_available' => ['nullable', 'boolean'],
            'branches.*.price_override' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    /**
     * CLAUDE.md §8: a branch can't offer a service in a module it doesn't
     * have enabled — the same tenant/branch module boundary that already
     * applies to UpdateBranchModulesRequest, checked here too since this
     * is a second place that invariant could otherwise be violated.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $service = $this->route('service');

            foreach ($this->input('branches', []) as $i => $row) {
                if (! ($row['is_available'] ?? false)) {
                    continue;
                }

                $branch = Branch::find($row['branch_id'] ?? null);

                if ($branch && ! $branch->hasModuleEnabled($service->module->code)) {
                    $validator->errors()->add(
                        "branches.{$i}.is_available",
                        "This branch does not have the {$service->module->name} module enabled.",
                    );
                }
            }
        });
    }
}
