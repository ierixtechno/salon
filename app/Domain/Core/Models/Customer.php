<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = [
        'name', 'email', 'phone', 'date_of_birth', 'gender',
        'tags', 'preferences', 'source', 'marketing_consent', 'is_active',
        'erasure_requested_at', 'erased_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'tags' => 'array',
            'preferences' => 'array',
            'marketing_consent' => 'boolean',
            'is_active' => 'boolean',
            'erasure_requested_at' => 'datetime',
            'erased_at' => 'datetime',
        ];
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(CustomerConsent::class)->latest();
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function customerMemberships(): HasMany
    {
        return $this->hasMany(CustomerMembership::class);
    }

    public function loyaltyLedgerEntries(): HasMany
    {
        return $this->hasMany(LoyaltyLedgerEntry::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function giftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class);
    }

    public function isErased(): bool
    {
        return $this->erased_at !== null;
    }

    /**
     * Never a mutable column — always the sum of the ledger (CLAUDE.md §21
     * Loyalty).
     */
    public function loyaltyPointsBalance(): int
    {
        return (int) $this->loyaltyLedgerEntries()->sum('points');
    }

    /**
     * Never a mutable column — always the sum of the ledger (CLAUDE.md §20
     * Wallet).
     */
    public function walletBalance(): string
    {
        return (string) $this->walletTransactions()->sum('amount');
    }
}
