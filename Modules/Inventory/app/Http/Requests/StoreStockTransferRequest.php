<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.manage');
    }

    public function rules(): array
    {
        return [
            'from_branch_id' => ['required', 'integer', 'different:to_branch_id'],
            'to_branch_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
