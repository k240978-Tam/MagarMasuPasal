<?php

namespace Modules\Products\Http\Controllers\Api;

use App\Http\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Products\Models\Product;

class ProductApiController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $products = Product::where('status', 'active')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->with('unit')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return $this->respond(
            $products->getCollection()->map($this->transform(...)),
            meta: ['total' => $products->total(), 'per_page' => $products->perPage(), 'current_page' => $products->currentPage()],
            links: ['next' => $products->nextPageUrl(), 'prev' => $products->previousPageUrl()],
        );
    }

    public function show(Product $product): JsonResponse
    {
        return $this->respond($this->transform($product->load('unit', 'categories')));
    }

    public function barcode(string $code): JsonResponse
    {
        $product = Product::where('barcode', $code)->with('unit')->first();

        if (! $product) {
            return $this->respond(['message' => 'No product with that barcode.'], status: 404);
        }

        return $this->respond($this->transform($product));
    }

    protected function transform(Product $product): array
    {
        return [
            'public_id' => $product->public_id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'unit_symbol' => $product->unit->symbol,
            'sell_by_weight' => $product->sell_by_weight,
            'selling_price' => (float) $product->selling_price,
            'status' => $product->status,
        ];
    }
}
