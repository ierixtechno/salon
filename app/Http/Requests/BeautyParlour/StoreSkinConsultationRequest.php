<?php

namespace App\Http\Requests\BeautyParlour;

use App\Domain\Core\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkinConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('beauty-consultations.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'consultant_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'consultation_date' => ['required', 'date', 'before_or_equal:today'],
            'concerns' => ['nullable', 'string', 'max:2000'],
            'treatment_plan' => ['nullable', 'string', 'max:2000'],
            'recommendation' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * CLAUDE.md §8/§9: a branch can only host a beauty consultation if it
     * actually has the beauty module enabled (same pattern as
     * StoreHairConsultationRequest / UpdateServiceBranchesRequest).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branch = Branch::find($this->input('branch_id'));

            if ($branch && ! $branch->hasModuleEnabled('beauty')) {
                $validator->errors()->add('branch_id', 'This branch does not have the Beauty Parlour module enabled.');
            }
        });
    }
}
