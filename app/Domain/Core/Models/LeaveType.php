<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'annual_days', 'is_paid', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Never a mutable balance column — always annual_days minus the sum of
     * this year's approved requests for this employee (CLAUDE.md §14).
     */
    public function remainingDaysFor(int $userId, int $year): ?int
    {
        if ($this->annual_days === null) {
            return null;
        }

        $used = $this->leaveRequests()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('days');

        return max(0, $this->annual_days - $used);
    }
}
