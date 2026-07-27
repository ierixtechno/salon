<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `status` is deliberately not in $fillable — set only via MarkAttendance/
 * ClockIn/ClockOut/ApproveLeave (CLAUDE.md §28).
 */
class AttendanceRecord extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['present', 'absent', 'half_day', 'on_leave'];

    protected $fillable = ['user_id', 'branch_id', 'date', 'check_in_at', 'check_out_at', 'notes', 'marked_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
