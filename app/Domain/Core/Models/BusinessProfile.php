<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'legal_name', 'display_name', 'logo_path',
        'contact_email', 'contact_phone', 'address', 'cancellation_policy',
    ];
}
