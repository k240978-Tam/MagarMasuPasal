<?php

namespace Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Categories\Models\Category;
use Modules\Customers\Models\Customer;
use Modules\PaymentManager\Services\PaymentManager;
use Modules\Products\Models\Product;
use Modules\Tenancy\Models\BranchTerminal;
use Symfony\Component\HttpFoundation\Response;

class PosScreenController extends Controller
{
    public function __invoke(BranchTerminal $terminal, Request $request, PaymentManager $payments): View
    {
        abort_unless($terminal->business_id === $request->user()->business_id, Response::HTTP_FORBIDDEN);

        $products = Product::where('business_id', $terminal->business_id)
            ->where('status', 'active')
            ->with('unit', 'categories')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'selling_price' => (float) $product->selling_price,
                'unit_symbol' => $product->unit->symbol,
                'sell_by_weight' => $product->sell_by_weight,
                'category_id' => $product->categories->first()?->id,
                'category_name' => $product->categories->first()?->name,
            ]);

        $categories = Category::where('business_id', $terminal->business_id)->orderBy('sort_order')->get(['id', 'name']);

        $customers = Customer::where('business_id', $terminal->business_id)
            ->with('group')
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'group_name' => $customer->group?->name,
                'allow_credit' => $customer->allowsCredit(),
            ]);

        return view('pos::screen', [
            'terminal' => $terminal,
            'products' => $products,
            'categories' => $categories,
            'customers' => $customers,
            'gateways' => $payments->availableGateways($terminal->business_id),
        ]);
    }
}
