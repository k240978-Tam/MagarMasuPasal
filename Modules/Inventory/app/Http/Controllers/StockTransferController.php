<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\StoreStockTransferRequest;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Services\StockTransferService;
use Modules\Products\Models\Product;
use Modules\Settings\Services\SettingsService;
use Modules\Tenancy\Models\Branch;
use RuntimeException;

class StockTransferController extends Controller
{
    protected function assertEnabled(int $businessId, SettingsService $settings): void
    {
        abort_unless($settings->isFeatureEnabled($businessId, 'stock_transfers'), 403, 'Stock transfers are disabled for this business.');
    }

    public function index(Request $request, SettingsService $settings): View
    {
        $businessId = $request->user()->business_id;
        $this->assertEnabled($businessId, $settings);

        $transfers = StockTransfer::withoutTenantScope()
            ->where('business_id', $businessId)
            ->with(['fromBranch', 'toBranch', 'items.product', 'createdBy'])
            ->latest()
            ->get();

        return view('inventory::transfers.index', [
            'transfers' => $transfers,
            'branches' => Branch::where('business_id', $businessId)->where('status', 'active')->get(),
            'products' => Product::where('business_id', $businessId)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreStockTransferRequest $request, StockTransferService $transfers, SettingsService $settings): RedirectResponse
    {
        $this->assertEnabled($request->user()->business_id, $settings);

        try {
            $transfers->initiate(
                businessId: $request->user()->business_id,
                fromBranchId: $request->integer('from_branch_id'),
                toBranchId: $request->integer('to_branch_id'),
                items: $request->input('items'),
                createdBy: $request->user()->id,
                notes: $request->input('notes'),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return back()->with('status', 'Transfer dispatched.');
    }

    public function receive(StockTransfer $transfer, Request $request, StockTransferService $transfers, SettingsService $settings): RedirectResponse
    {
        abort_unless($transfer->business_id === $request->user()->business_id, 403);
        $this->assertEnabled($transfer->business_id, $settings);

        try {
            $transfers->receive($transfer, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('status', 'Transfer received — stock updated at both branches.');
    }

    public function cancel(StockTransfer $transfer, Request $request, StockTransferService $transfers, SettingsService $settings): RedirectResponse
    {
        abort_unless($transfer->business_id === $request->user()->business_id, 403);
        $this->assertEnabled($transfer->business_id, $settings);

        $transfers->cancel($transfer);

        return back()->with('status', 'Transfer cancelled.');
    }
}
