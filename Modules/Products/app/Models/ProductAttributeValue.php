<?php

namespace Modules\Products\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Settings\Models\ProductAttributeDefinition;

class ProductAttributeValue extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'product_id',
        'attribute_definition_id',
        'value',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function definition()
    {
        return $this->belongsTo(ProductAttributeDefinition::class, 'attribute_definition_id');
    }
}
