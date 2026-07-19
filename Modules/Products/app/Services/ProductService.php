<?php

namespace Modules\Products\Services;

use Illuminate\Support\Facades\DB;
use Modules\Products\Models\Product;

/**
 * Owns product create/update, including syncing categories and the
 * tenant's configurable attribute values in one transaction. Controllers
 * (web and API) call this instead of touching Product directly.
 */
class ProductService
{
    public function create(array $attributes, array $categoryIds = [], array $attributeValues = []): Product
    {
        return DB::transaction(function () use ($attributes, $categoryIds, $attributeValues) {
            $product = Product::create($attributes);

            if ($categoryIds) {
                $product->categories()->sync($categoryIds);
            }

            $this->syncAttributeValues($product, $attributeValues);

            return $product->fresh(['unit', 'categories', 'attributeValues']);
        });
    }

    public function update(Product $product, array $attributes, ?array $categoryIds = null, ?array $attributeValues = null): Product
    {
        return DB::transaction(function () use ($product, $attributes, $categoryIds, $attributeValues) {
            $product->update($attributes);

            if ($categoryIds !== null) {
                $product->categories()->sync($categoryIds);
            }

            if ($attributeValues !== null) {
                $this->syncAttributeValues($product, $attributeValues);
            }

            return $product->fresh(['unit', 'categories', 'attributeValues']);
        });
    }

    /**
     * @param  array<int, string|null>  $attributeValues  keyed by attribute_definition_id
     */
    protected function syncAttributeValues(Product $product, array $attributeValues): void
    {
        foreach ($attributeValues as $definitionId => $value) {
            if ($value === null || $value === '') {
                $product->attributeValues()->where('attribute_definition_id', $definitionId)->delete();

                continue;
            }

            $product->attributeValues()->updateOrCreate(
                ['attribute_definition_id' => $definitionId],
                ['value' => $value],
            );
        }
    }

    public function findByBarcode(int $businessId, string $barcode): ?Product
    {
        return Product::withoutTenantScope()
            ->where('business_id', $businessId)
            ->where('barcode', $barcode)
            ->where('status', 'active')
            ->first();
    }
}
