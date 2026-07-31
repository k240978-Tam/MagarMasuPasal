<?php

namespace Modules\Products\Models;

use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\AuditLog\Traits\Auditable;
use Modules\Categories\Models\Category;
use Modules\Settings\Models\TaxRule;
use Modules\Units\Models\Unit;

class Product extends Model
{
    use Auditable, BelongsToTenant, HasPublicId, SoftDeletes;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'unit_id',
        'sell_by_weight',
        'track_batches',
        'track_expiry',
        'cost_price',
        'selling_price',
        'min_stock',
        'max_stock',
        'default_supplier_id',
        'image_media_id',
        'status',
        'tax_rule_id',
    ];

    protected $casts = [
        'sell_by_weight' => 'boolean',
        'track_batches' => 'boolean',
        'track_expiry' => 'boolean',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'min_stock' => 'decimal:3',
        'max_stock' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name).'-'.Str::lower(Str::random(6));
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category');
    }

    public function branchSettings(): HasMany
    {
        return $this->hasMany(ProductBranchSetting::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }
}
