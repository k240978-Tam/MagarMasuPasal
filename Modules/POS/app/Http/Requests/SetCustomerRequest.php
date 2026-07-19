<?php

namespace Modules\POS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pos.operate');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
        ];
    }
}
