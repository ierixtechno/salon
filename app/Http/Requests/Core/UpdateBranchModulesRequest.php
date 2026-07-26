<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('branches.update');
    }

    public function rules(): array
    {
        // CLAUDE.md §8: a branch cannot enable a module its tenant doesn't
        // have — enforced here, not just filtered at the UI layer.
        $tenantModuleCodes = current_tenant()->tenantModules()
            ->where('enabled', true)
            ->with('module')
            ->get()
            ->pluck('module.code');

        return [
            'modules' => ['present', 'array'],
            'modules.*' => [Rule::in($tenantModuleCodes)],
        ];
    }
}
