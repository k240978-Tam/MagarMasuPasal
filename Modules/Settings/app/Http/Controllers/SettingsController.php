<?php

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Settings\Http\Requests\UpdateFeatureTogglesRequest;
use Modules\Settings\Http\Requests\UpdateReceiptTemplateRequest;
use Modules\Settings\Models\TaxRule;
use Modules\Settings\Services\SettingsService;

class SettingsController extends Controller
{
    public function index(Request $request, SettingsService $settings): View
    {
        $businessId = $request->user()->business_id;

        $taxRules = TaxRule::withoutTenantScope()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get();

        $receiptTemplate = array_merge(
            ['header_note' => '', 'footer_text' => 'Thank you for shopping with us!', 'show_qr' => true],
            $settings->get($businessId, 'receipt_template', []),
        );

        $enabledFlags = $settings->get($businessId, 'features', []);

        return view('settings::index', [
            'business' => $request->user()->business,
            'taxRules' => $taxRules,
            'receiptTemplate' => $receiptTemplate,
            'featureFlags' => SettingsService::FEATURE_FLAGS,
            'enabledFlags' => $enabledFlags,
        ]);
    }

    /**
     * Seller identity as it must appear on a tax invoice. IRD requires the
     * registered name and PAN/VAT number on every invoice the shop issues.
     */
    public function updateBusinessProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:150'],
            'pan_vat_number' => ['nullable', 'string', 'max:30'],
        ]);

        $request->user()->business->update($validated);

        return back()->with('status', 'Business tax details updated.');
    }

    public function updateReceiptTemplate(UpdateReceiptTemplateRequest $request, SettingsService $settings): RedirectResponse
    {
        $settings->set($request->user()->business_id, 'receipt_template', [
            'header_note' => $request->input('header_note', ''),
            'footer_text' => $request->input('footer_text') ?: 'Thank you for shopping with us!',
            'show_qr' => $request->boolean('show_qr'),
        ]);

        return back()->with('status', 'Receipt template updated.');
    }

    public function updateFeatureToggles(UpdateFeatureTogglesRequest $request, SettingsService $settings): RedirectResponse
    {
        $enabled = $request->input('flags', []);

        $overrides = [];
        foreach (array_keys(SettingsService::FEATURE_FLAGS) as $flag) {
            $overrides[$flag] = in_array($flag, $enabled, true);
        }

        $settings->set($request->user()->business_id, 'features', $overrides);

        return back()->with('status', 'Feature toggles updated.');
    }
}
