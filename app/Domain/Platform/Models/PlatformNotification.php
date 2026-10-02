<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An in-app alert for one Super Admin (new quotation, payment received,
 * upcoming renewal ...). Platform-level, so not tenant-scoped: every read is
 * filtered by the signed-in admin's own id.
 */
class PlatformNotification extends Model
{
    protected $fillable = ['platform_admin_id', 'kind', 'title', 'body', 'url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
