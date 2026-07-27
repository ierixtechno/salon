<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = [
        'product_category_id', 'name', 'sku', 'brand', 'unit',
        'cost_price', 'selling_price', 'reorder_level', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function branchStocks(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Reorder threshold at a given branch: the branch's own override if
     * one was set, else the product's tenant-wide default.
     */
    public function reorderLevelFor(Branch $branch): string
    {
        $stock = $this->branchStocks->firstWhere('branch_id', $branch->id);

        return $stock?->reorder_level_override ?? $this->reorder_level;
    }
}
