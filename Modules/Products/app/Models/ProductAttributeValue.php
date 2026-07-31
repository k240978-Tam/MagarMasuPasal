<?php

namespace Modules\Products\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductAttributeDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeDefinition::class, 'attribute_definition_id');
    }
}
