<?php

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Settings\Http\Requests\StoreTaxRuleRequest;
use Modules\Settings\Models\TaxRule;

class TaxRuleController extends Controller
{
    public function store(StoreTaxRuleRequest $request): RedirectResponse
    {
        TaxRule::create([
            'business_id' => $request->user()->business_id,
            'name' => $request->string('name'),
            'rate' => $request->float('rate'),
            'is_active' => true,
        ]);

        return back()->with('status', 'Tax rule added.');
    }

    public function toggle(TaxRule $taxRule, Request $request): RedirectResponse
    {
        abort_unless($taxRule->business_id === $request->user()->business_id, 403);
        abort_unless($request->user()->can('settings.manage'), 403);

        $taxRule->update(['is_active' => ! $taxRule->is_active]);

        return back()->with('status', 'Tax rule updated.');
    }

    public function destroy(TaxRule $taxRule, Request $request): RedirectResponse
    {
        abort_unless($taxRule->business_id === $request->user()->business_id, 403);
        abort_unless($request->user()->can('settings.manage'), 403);

        $taxRule->delete();

        return back()->with('status', 'Tax rule removed.');
    }
}
