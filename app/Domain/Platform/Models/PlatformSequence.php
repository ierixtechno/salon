<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSequence extends Model
{
    protected $fillable = ['sequence_type', 'financial_year', 'next_number'];
}
