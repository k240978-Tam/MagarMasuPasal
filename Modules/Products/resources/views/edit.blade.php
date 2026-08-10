<x-layouts.app title="Edit Product">
    <div class="flex flex-col gap-6 max-w-2xl">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Edit Product</h1>
            <p class="mt-1 text-sm text-ink-soft">Changes to price and tax apply to new sales immediately — on both the POS and the online store.</p>
        </div>

        <form method="POST" action="{{ route('products.update', $product) }}" class="rounded-xl border border-line bg-surface p-6">
            @csrf
            @method('PATCH')

            @include('products::partials.form-fields')

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-line pt-6">
                <a href="{{ route('products.index') }}" class="rounded-lg border border-line px-4 py-2 text-sm font-semibold text-ink">Cancel</a>
                <button type="submit" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-ink">Save Changes</button>
            </div>
        </form>

        <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Archive {{ $product->name }}? It will disappear from the POS and online store, but past sales keep their records.')" class="rounded-xl border border-critical/40 bg-surface p-6">
            @csrf
            @method('DELETE')
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Archive this product</h2>
                    <p class="mt-1 text-xs text-ink-soft">Removes it from selling screens without deleting sales history.</p>
                </div>
                <button type="submit" class="shrink-0 rounded-lg border border-critical px-4 py-2 text-sm font-semibold text-critical">Archive</button>
            </div>
        </form>
    </div>
</x-layouts.app>
