<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Core\Concerns\BelongsToTenant;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\CommissionRule;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active', 'all_branches'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'all_branches' => 'boolean',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user')->withTimestamps();
    }

    public function commissionRule(): HasOne
    {
        return $this->hasOne(CommissionRule::class);
    }

    /**
     * Server-side branch access check — never trust a client-supplied
     * branch_id (CLAUDE.md §13). `all_branches` grants every branch under
     * this user's own tenant; otherwise access is explicit via branch_user.
     */
    public function canAccessBranch(Branch $branch): bool
    {
        if ($branch->tenant_id !== $this->tenant_id) {
            return false;
        }

        if ($this->all_branches) {
            return true;
        }

        return $this->branches()->whereKey($branch->id)->exists();
    }
}
