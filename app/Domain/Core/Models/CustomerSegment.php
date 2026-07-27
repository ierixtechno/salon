<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerSegment extends Model
{
    use BelongsToTenant;

    public const TYPES = ['all', 'tag', 'inactive_days'];

    protected $fillable = ['name', 'type', 'criteria'];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
        ];
    }
}
