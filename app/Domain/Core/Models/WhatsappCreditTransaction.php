<?php

namespace App\Domain\Core\Models;

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deliberately NOT BelongsToTenant — like Quotation/PlatformInvoice, this
 * is a platform-owned record viewed from two different guards: Super
 * Admin tops up any tenant's balance (no "current tenant" session to
 * auto-scope to), while a tenant only ever sees their own. Every caller
 * must explicitly filter/check tenant_id itself (CLAUDE.md §32 IDOR
 * prevention) — see Tenant::whatsappCreditBalance() for the one place
 * that does.
 */
class WhatsappCreditTransaction extends Model
{
    public const TYPES = ['credit', 'debit'];

    protected $fillable = [
        'tenant_id', 'type', 'amount', 'reference_type', 'reference_id', 'reason', 'platform_admin_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }
}
