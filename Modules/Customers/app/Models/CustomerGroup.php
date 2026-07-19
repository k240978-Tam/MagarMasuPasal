<?php

namespace Modules\Customers\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

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

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
