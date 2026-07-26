<?php

namespace App\Http\Requests\Spa;

use App\Domain\Core\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpaConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('spa-consultations.create');
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
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * CLAUDE.md §8/§9: a branch can only host a spa consultation if it
     * actually has the spa module enabled (same pattern as
     * StoreHairConsultationRequest / UpdateServiceBranchesRequest).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branch = Branch::find($this->input('branch_id'));

            if ($branch && ! $branch->hasModuleEnabled('spa')) {
                $validator->errors()->add('branch_id', 'This branch does not have the Spa module enabled.');
            }
        });
    }
}
