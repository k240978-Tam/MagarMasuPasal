<?php

namespace Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.manage');
    }

    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products')->where('business_id', $businessId)->whereNull('deleted_at')],
            'barcode' => ['nullable', 'string', 'max:60'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where('business_id', $businessId)],
            'categories' => ['nullable', 'array'],
            'categories.*' => [Rule::exists('categories', 'id')->where('business_id', $businessId)],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'sell_by_weight' => ['sometimes', 'boolean'],
            'track_expiry' => ['sometimes', 'boolean'],
            'tax_rule_id' => ['nullable', Rule::exists('tax_rules', 'id')->where('business_id', $businessId)->where('is_active', true)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'A product with this SKU already exists in your business.',
            'tax_rule_id.exists' => 'The selected tax rule is invalid or inactive.',
        ];
    }
}
