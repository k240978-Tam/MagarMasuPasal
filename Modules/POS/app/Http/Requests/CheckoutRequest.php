<?php

namespace Modules\POS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pos.operate');
    }

    public function rules(): array
    {
        return [
            'sale_type' => ['required', 'in:retail,credit'],
            // A credit sale can be billed to the customer's account with no
            // payment at all (or a partial down-payment); a retail sale must
            // be paid in full at the register.
            'payments' => $this->input('sale_type') === 'credit' ? ['array'] : ['required', 'array', 'min:1'],
            'payments.*.gateway_key' => ['required', 'string'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
