<?php

namespace App\Domain\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One browser/phone a user has allowed push alerts on. Deliberately NOT
 * BelongsToTenant: it is read by queue jobs with no session, and the job
 * always filters by the explicit tenant_id + user_id of the notification it
 * is delivering. The HTTP endpoints only ever touch the signed-in user's own
 * rows (CLAUDE.md §11/§32).
 */
class PushSubscription extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth_token', 'user_agent', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
