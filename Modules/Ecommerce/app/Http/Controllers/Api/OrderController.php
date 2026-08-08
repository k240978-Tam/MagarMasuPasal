<?php

namespace Modules\Ecommerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Ecommerce\Models\OnlineOrder;
use Modules\Ecommerce\Models\OnlineOrderItem;
use Modules\Inventory\Models\InventoryStock;
use Modules\Products\Models\Product;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $businessId = $request->query('business_id');
        $branchId = $request->query('branch_id');

        if (! $businessId || ! $branchId) {
            return response()->json(['error' => 'business_id and branch_id required'], 400);
        }

        try {
            $validated = $request->validate([
                'customer_name' => ['required', 'string', 'max:150'],
                'customer_email' => ['required', 'email', 'max:255'],
                'customer_phone' => ['required', 'string', 'max:30'],
                'delivery_address' => ['required', 'string'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'string'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
        }

        $subtotal = 0;
        $taxAmount = 0;
        $orderItems = [];

        foreach ($validated['items'] as $item) {
            $product = Product::where('business_id', $businessId)
                ->where('public_id', $item['product_id'])
                ->first();

            if (! $product) {
                return response()->json(['error' => "Product {$item['product_id']} not found"], 404);
            }

            $stock = InventoryStock::where('business_id', $businessId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->first();

            if (! $stock || $stock->availableQty() < $item['quantity']) {
                return response()->json(
                    ['error' => "Insufficient stock for {$product->name}"],
                    400
                );
            }

            $lineTotal = $product->selling_price * $item['quantity'];
            $taxRate = $product->taxRule?->rate ?? 0;
            $taxLineAmount = ($lineTotal * $taxRate) / 100;

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $product->selling_price,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxLineAmount,
                'line_total' => $lineTotal,
            ];

            $subtotal += $lineTotal;
            $taxAmount += $taxLineAmount;
        }

        $order = OnlineOrder::create([
            'business_id' => $businessId,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'delivery_address' => $validated['delivery_address'],
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal + $taxAmount,
        ]);

        foreach ($orderItems as $item) {
            OnlineOrderItem::create(array_merge($item, [
                'online_order_id' => $order->id,
            ]));

            InventoryStock::where('business_id', $businessId)
                ->where('branch_id', $branchId)
                ->where('product_id', $item['product_id'])
                ->decrement('quantity_on_hand', $item['quantity']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => [
                'id' => $order->public_id,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
            ],
        ], 201);
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $businessId = $request->query('business_id');

        if (! $businessId) {
            return response()->json(['error' => 'business_id required'], 400);
        }

        $order = OnlineOrder::where('business_id', $businessId)
            ->where('public_id', $publicId)
            ->with('items.product')
            ->first();

        if (! $order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->public_id,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'delivery_address' => $order->delivery_address,
                'status' => $order->status,
                'subtotal' => $order->subtotal,
                'tax_amount' => $order->tax_amount,
                'total_amount' => $order->total_amount,
                'items' => $order->items->map(fn (OnlineOrderItem $item) => [
                    'product_name' => $item->product?->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount,
                    'line_total' => $item->line_total,
                ]),
                'created_at' => $order->created_at,
            ],
        ]);
    }
}
