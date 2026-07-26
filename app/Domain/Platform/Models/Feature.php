<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = ['code', 'name', 'description'];
}
