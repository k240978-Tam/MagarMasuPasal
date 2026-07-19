<?php

namespace Modules\POS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pos.operate');
    }

    public function rules(): array
    {
        return [
            'percent' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}
