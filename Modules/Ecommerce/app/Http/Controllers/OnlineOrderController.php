<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Ecommerce\Models\OnlineOrder;

class OnlineOrderController extends Controller
{
    public function index(Request $request): View
    {
        $businessId = $request->user()->business_id;

        $orders = OnlineOrder::where('business_id', $businessId)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('ecommerce::orders.index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, OnlineOrder $order): View
    {
        $request->user()->business_id === $order->business_id || abort(403);

        $order->load('items.product');

        return view('ecommerce::orders.show', [
            'order' => $order,
        ]);
    }

    public function updateStatus(Request $request, OnlineOrder $order): RedirectResponse
    {
        $request->user()->business_id === $order->business_id || abort(403);

        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,shipped,delivered,cancelled'],
        ]);

        $statusMap = [
            'confirmed' => 'confirmed_at',
            'shipped' => 'shipped_at',
            'delivered' => 'delivered_at',
        ];

        if ($statusMap[$validated['status']] ?? null) {
            $order->{$statusMap[$validated['status']]} = now();
        }

        $order->status = $validated['status'];
        $order->save();

        return back()->with('status', 'Order status updated.');
    }
}
