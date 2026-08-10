<x-layouts.app title="Products">
    <div class="flex flex-col gap-6 max-w-5xl">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-ink text-balance">Products</h1>
                <p class="mt-1 text-sm text-ink-soft">Manage your catalog: prices, tax, categories, and availability for the POS and online store.</p>
            </div>
            <a href="{{ route('products.create') }}" class="shrink-0 rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-ink">Add Product</a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-line bg-surface px-4 py-2 text-sm text-ink">{{ session('status') }}</div>
        @endif

        <form method="GET" action="{{ route('products.index') }}" class="flex gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, SKU, or barcode..." class="w-full max-w-sm rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm">
            <button type="submit" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink">Search</button>
        </form>

        @if ($products->isEmpty())
            <div class="rounded-xl border border-line bg-surface p-8 text-center">
                <p class="text-ink-soft">{{ $search === '' ? 'No products yet. Add your first product to get started.' : 'No products match your search.' }}</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-line">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-surface text-left text-xs uppercase tracking-wide text-ink-soft">
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Unit</th>
                            <th class="px-4 py-3 text-right">Cost</th>
                            <th class="px-4 py-3 text-right">Price</th>
                            <th class="px-4 py-3">Tax</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr class="border-t border-line">
                                <td class="px-4 py-3 font-medium">{{ $product->name }}</td>
                                <td class="px-4 py-3 text-ink-soft">{{ $product->sku ?: '—' }}</td>
                                <td class="px-4 py-3 text-ink-soft">{{ $product->categories->pluck('name')->join(', ') ?: '—' }}</td>
                                <td class="px-4 py-3 text-ink-soft">{{ $product->unit?->symbol }}</td>
                                <td class="px-4 py-3 text-right text-ink-soft">Rs {{ number_format((float) $product->cost_price, 2) }}</td>
                                <td class="px-4 py-3 text-right font-medium">Rs {{ number_format((float) $product->selling_price, 2) }}</td>
                                <td class="px-4 py-3 text-ink-soft">{{ $product->taxRule ? $product->taxRule->name.' ('.rtrim(rtrim(number_format((float) $product->taxRule->rate, 2), '0'), '.').'%)' : 'None' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $product->status === 'active' ? 'bg-positive/10 text-positive' : 'bg-ink-soft/10 text-ink-soft' }}">
                                        {{ ucfirst($product->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('products.edit', $product) }}" class="text-sm font-semibold text-accent">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $products->links() }}
        @endif
    </div>
</x-layouts.app>
