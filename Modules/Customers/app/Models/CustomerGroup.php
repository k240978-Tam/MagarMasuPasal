<?php

namespace Modules\Customers\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'name',
        'allow_credit',
        'credit_limit',
        'default_discount_percent',
    ];

    protected $casts = [
        'allow_credit' => 'boolean',
        'credit_limit' => 'decimal:2',
        'default_discount_percent' => 'decimal:2',
    ];

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
