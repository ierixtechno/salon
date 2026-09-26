<?php

namespace App\Rules;

use App\Domain\Platform\Models\SubscriptionPlan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A requested branch count may not exceed what the chosen plan sells. Asking
 * for fewer than the plan includes is fine — it is simply raised to the
 * included number (SubscriptionPlan::clampBranches).
 */
class BranchCountWithinPlan implements ValidationRule
{
    public function __construct(private readonly mixed $planId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $plan = is_numeric($this->planId) ? SubscriptionPlan::find((int) $this->planId) : null;

        if ($plan && (int) $value > $plan->maxBranches()) {
            $fail($plan->sellsExtraBranches()
                ? "This package allows at most {$plan->maxBranches()} branches."
                : "This package includes {$plan->branch_limit} ".str('branch')->plural($plan->branch_limit).' and does not offer additional ones.');
        }
    }
}
