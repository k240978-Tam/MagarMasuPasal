<?php

namespace Modules\Ecommerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryItem;
use Modules\Products\Models\Product;

class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businessId = $request->query('business_id');
        $branchId = $request->query('branch_id');

        if (! $businessId || ! $branchId) {
            return response()->json(['error' => 'business_id and branch_id required'], 400);
        }

        $products = Product::where('business_id', $businessId)
            ->where('status', 'active')
            ->with(['category', 'unit'])
            ->get()
            ->map(function (Product $product) use ($businessId, $branchId) {
                $inventory = InventoryItem::where('business_id', $businessId)
                    ->where('branch_id', $branchId)
                    ->where('product_id', $product->id)
                    ->first();

                return [
                    'id' => $product->public_id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'category' => $product->category?->name,
                    'unit' => $product->unit?->code,
                    'price' => $product->selling_price,
                    'cost_price' => $product->cost_price,
                    'stock' => $inventory?->quantity ?? 0,
                    'has_expiry' => $product->has_expiry,
                    'weight' => $product->weight,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $products,
            'total' => $products->count(),
        ]);
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $businessId = $request->query('business_id');
        $branchId = $request->query('branch_id');

        if (! $businessId || ! $branchId) {
            return response()->json(['error' => 'business_id and branch_id required'], 400);
        }

        $product = Product::where('business_id', $businessId)
            ->where('public_id', $publicId)
            ->with(['category', 'unit'])
            ->first();

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $inventory = InventoryItem::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->public_id,
                'name' => $product->name,
                'description' => $product->description,
                'category' => $product->category?->name,
                'unit' => $product->unit?->code,
                'price' => $product->selling_price,
                'cost_price' => $product->cost_price,
                'stock' => $inventory?->quantity ?? 0,
                'has_expiry' => $product->has_expiry,
                'weight' => $product->weight,
            ],
        ]);
    }
}
