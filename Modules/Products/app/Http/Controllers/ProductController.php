<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Categories\Models\Category;
use Modules\Products\Http\Requests\StoreProductRequest;
use Modules\Products\Http\Requests\UpdateProductRequest;
use Modules\Products\Models\Product;
use Modules\Settings\Models\TaxRule;
use Modules\Units\Models\Unit;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $businessId = $request->user()->business_id;
        $search = trim((string) $request->query('q', ''));
        $term = '%'.mb_strtolower($search).'%';

        $products = Product::where('business_id', $businessId)
            ->with(['unit', 'categories', 'taxRule'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$term])
                ->orWhereRaw('LOWER(barcode) LIKE ?', [$term])))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('products::index', [
            'products' => $products,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        return view('products::create', $this->formOptions($request));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['business_id'] = $request->user()->business_id;
        $categoryIds = $validated['categories'] ?? [];
        unset($validated['categories']);

        $product = Product::create($validated);
        $product->categories()->sync($categoryIds);

        return redirect()->route('products.index')->with('status', "Product \"{$product->name}\" created.");
    }

    public function edit(Request $request, Product $product): View
    {
        abort_unless($product->business_id === $request->user()->business_id, Response::HTTP_FORBIDDEN);

        $product->load('categories');

        return view('products::edit', ['product' => $product] + $this->formOptions($request));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        abort_unless($product->business_id === $request->user()->business_id, Response::HTTP_FORBIDDEN);

        $validated = $request->validated();
        $categoryIds = $validated['categories'] ?? [];
        unset($validated['categories']);

        // An unchecked checkbox sends nothing, so absent booleans mean false.
        $validated['sell_by_weight'] = $request->boolean('sell_by_weight');
        $validated['track_expiry'] = $request->boolean('track_expiry');
        $validated['tax_rule_id'] = $validated['tax_rule_id'] ?? null;

        $product->update($validated);
        $product->categories()->sync($categoryIds);

        return redirect()->route('products.index')->with('status', "Product \"{$product->name}\" updated.");
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->business_id === $request->user()->business_id, Response::HTTP_FORBIDDEN);

        $product->delete();

        return redirect()->route('products.index')->with('status', "Product \"{$product->name}\" archived.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(Request $request): array
    {
        $businessId = $request->user()->business_id;

        return [
            'units' => Unit::where('business_id', $businessId)->orderBy('name')->get(),
            'categories' => Category::where('business_id', $businessId)->orderBy('sort_order')->get(),
            'taxRules' => TaxRule::where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
