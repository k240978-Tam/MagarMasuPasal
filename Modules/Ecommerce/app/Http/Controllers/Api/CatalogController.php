<?php

namespace Modules\Ecommerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryStock;
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
            ->with(['categories', 'unit'])
            ->get()
            ->map(fn (Product $product) => $this->presentProduct($product, $businessId, $branchId));

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
            ->with(['categories', 'unit'])
            ->first();

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->presentProduct($product, $businessId, $branchId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentProduct(Product $product, mixed $businessId, mixed $branchId): array
    {
        $stock = InventoryStock::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->first();

        return [
            'id' => $product->public_id,
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->categories->first()?->name,
            'unit' => $product->unit?->symbol,
            'price' => $product->selling_price,
            'stock' => $stock?->availableQty() ?? 0.0,
            'has_expiry' => (bool) $product->track_expiry,
            'sell_by_weight' => (bool) $product->sell_by_weight,
        ];
    }
}
