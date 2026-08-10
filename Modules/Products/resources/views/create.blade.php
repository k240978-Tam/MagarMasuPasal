<x-layouts.app title="Add Product">
    <div class="flex flex-col gap-6 max-w-2xl">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Add Product</h1>
            <p class="mt-1 text-sm text-ink-soft">New products appear on the POS and online store once active. Stock starts at zero — receive it via a purchase or stock adjustment.</p>
        </div>

        <form method="POST" action="{{ route('products.store') }}" class="rounded-xl border border-line bg-surface p-6">
            @csrf

            @include('products::partials.form-fields')

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-line pt-6">
                <a href="{{ route('products.index') }}" class="rounded-lg border border-line px-4 py-2 text-sm font-semibold text-ink">Cancel</a>
                <button type="submit" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-ink">Create Product</button>
            </div>
        </form>
    </div>
</x-layouts.app>
