<?php

namespace Modules\POS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pos.operate');
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
