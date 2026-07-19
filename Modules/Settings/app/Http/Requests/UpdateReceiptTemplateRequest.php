<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'header_note' => ['nullable', 'string', 'max:200'],
            'footer_text' => ['nullable', 'string', 'max:200'],
            'show_qr' => ['nullable', 'boolean'],
        ];
    }
}
