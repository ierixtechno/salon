<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): PackageFactory
    {
        return PackageFactory::new();
    }

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = ['name', 'description', 'validity_days', 'price', 'tax_rate_percent', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tax_rate_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'package_services')->withPivot('quantity')->withTimestamps();
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }
}
