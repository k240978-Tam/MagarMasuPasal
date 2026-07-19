<?php

namespace Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Seeded reference data (meat_shop, grocery, pharmacy, ...). Never branched on
 * in application code — it only carries default configuration that Settings
 * copies into a business's own configurable attributes/units on creation.
 */
class BusinessType extends Model
{
    protected $fillable = [
        'key',
        'name',
        'default_config',
    ];

    protected $casts = [
        'default_config' => 'array',
    ];

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }
}
