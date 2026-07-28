<?php

namespace App\Http\Requests\Tattoo;

use App\Domain\Core\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTattooConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tattoo-consultations.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'consultant_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'consultation_date' => ['required', 'date', 'before_or_equal:today'],
            'design_description' => ['nullable', 'string', 'max:2000'],
            'placement' => ['nullable', 'string', 'max:100'],
            'size_estimate' => ['nullable', 'string', 'max:100'],
            'aftercare_instructions' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * CLAUDE.md §8/§9: a branch can only host a tattoo consultation if it
     * actually has the tattoo module enabled (same pattern as
     * StoreSpaConsultationRequest).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branch = Branch::find($this->input('branch_id'));

            if ($branch && ! $branch->hasModuleEnabled('tattoo')) {
                $validator->errors()->add('branch_id', 'This branch does not have the Tattoo Studio module enabled.');
            }
        });
    }
}
