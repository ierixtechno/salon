<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use BelongsToTenant;

    public const TYPES = ['sale', 'refund', 'expense', 'cash_in', 'cash_out'];

    protected $fillable = [
        'branch_id', 'cash_register_session_id', 'type', 'amount',
        'reference_type', 'reference_id', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
