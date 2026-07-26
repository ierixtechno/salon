<?php

namespace App\Http\Requests\Salon;

use App\Domain\Core\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHairConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('salon-consultations.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'consultant_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'consultation_date' => ['required', 'date', 'before_or_equal:today'],
            'concerns' => ['nullable', 'string', 'max:2000'],
            'recommendation' => ['nullable', 'string', 'max:2000'],
            'color_formula' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * CLAUDE.md §8/§9: a branch can only host a salon consultation if it
     * actually has the salon module enabled — the tenant-level `module:salon`
     * route middleware alone isn't enough since this route carries no
     * {branch} parameter for it to inspect (same pattern as
     * UpdateServiceBranchesRequest).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branch = Branch::find($this->input('branch_id'));

            if ($branch && ! $branch->hasModuleEnabled('salon')) {
                $validator->errors()->add('branch_id', 'This branch does not have the Salon module enabled.');
            }
        });
    }
}
