<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Settings\Services\SettingsService;

class UpdateFeatureTogglesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'flags' => ['nullable', 'array'],
            'flags.*' => ['in:'.implode(',', array_keys(SettingsService::FEATURE_FLAGS))],
        ];
    }
}
