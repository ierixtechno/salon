<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    use BelongsToTenant;

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract'];

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = [
        'user_id', 'job_title', 'employment_type', 'hire_date', 'phone', 'skills',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'skills' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
