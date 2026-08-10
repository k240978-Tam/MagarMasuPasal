@php
    /** @var \Modules\Products\Models\Product|null $product */
    $product = $product ?? null;
    $selectedCategories = collect(old('categories', $product?->categories->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
@endphp

<div class="flex flex-col gap-6">
    <div>
        <label for="name" class="block text-sm font-semibold text-ink">Name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $product?->name) }}" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('name') border-critical @enderror" placeholder="e.g. Chicken Boneless">
        @error('name')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="sku" class="block text-sm font-semibold text-ink">SKU <span class="text-ink-soft">(Optional)</span></label>
            <input type="text" id="sku" name="sku" value="{{ old('sku', $product?->sku) }}" class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('sku') border-critical @enderror" placeholder="e.g. CHK-BNL">
            @error('sku')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="barcode" class="block text-sm font-semibold text-ink">Barcode <span class="text-ink-soft">(Optional)</span></label>
            <input type="text" id="barcode" name="barcode" value="{{ old('barcode', $product?->barcode) }}" class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('barcode') border-critical @enderror">
            @error('barcode')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="unit_id" class="block text-sm font-semibold text-ink">Unit</label>
            <select id="unit_id" name="unit_id" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('unit_id') border-critical @enderror">
                <option value="">Select a unit...</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected((int) old('unit_id', $product?->unit_id) === $unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>
                @endforeach
            </select>
            @error('unit_id')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="tax_rule_id" class="block text-sm font-semibold text-ink">Tax Rule <span class="text-ink-soft">(Optional)</span></label>
            <select id="tax_rule_id" name="tax_rule_id" class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('tax_rule_id') border-critical @enderror">
                <option value="">No tax</option>
                @foreach ($taxRules as $rule)
                    <option value="{{ $rule->id }}" @selected((int) old('tax_rule_id', $product?->tax_rule_id) === $rule->id)>{{ $rule->name }} — {{ rtrim(rtrim(number_format((float) $rule->rate, 2), '0'), '.') }}%</option>
                @endforeach
            </select>
            @error('tax_rule_id')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
            @if ($taxRules->isEmpty())
                <p class="mt-1 text-xs text-ink-soft">No active tax rules. Create one in Settings first if this product is taxable.</p>
            @endif
        </div>
    </div>

    <div>
        <span class="block text-sm font-semibold text-ink">Categories</span>
        <div class="mt-2 flex flex-wrap gap-3">
            @forelse ($categories as $category)
                <label class="inline-flex items-center gap-2 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm">
                    <input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategories, true)) class="rounded border-line">
                    {{ $category->name }}
                </label>
            @empty
                <p class="text-sm text-ink-soft">No categories yet.</p>
            @endforelse
        </div>
        @error('categories')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="cost_price" class="block text-sm font-semibold text-ink">Cost Price (Rs)</label>
            <input type="number" step="0.01" min="0" id="cost_price" name="cost_price" value="{{ old('cost_price', $product?->cost_price) }}" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('cost_price') border-critical @enderror">
            @error('cost_price')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="selling_price" class="block text-sm font-semibold text-ink">Selling Price (Rs)</label>
            <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" value="{{ old('selling_price', $product?->selling_price) }}" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('selling_price') border-critical @enderror">
            @error('selling_price')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="min_stock" class="block text-sm font-semibold text-ink">Minimum Stock <span class="text-ink-soft">(low-stock alert level)</span></label>
            <input type="number" step="0.001" min="0" id="min_stock" name="min_stock" value="{{ old('min_stock', $product?->min_stock) }}" class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('min_stock') border-critical @enderror">
            @error('min_stock')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="status" class="block text-sm font-semibold text-ink">Status</label>
            <select id="status" name="status" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('status') border-critical @enderror">
                <option value="active" @selected(old('status', $product?->status ?? 'active') === 'active')>Active — shown on POS and online store</option>
                <option value="inactive" @selected(old('status', $product?->status) === 'inactive')>Inactive — hidden everywhere</option>
            </select>
            @error('status')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex flex-wrap gap-6">
        <label class="inline-flex items-center gap-2 text-sm text-ink">
            <input type="checkbox" name="sell_by_weight" value="1" @checked(old('sell_by_weight', $product?->sell_by_weight)) class="rounded border-line">
            Sold by weight (e.g. per kg)
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-ink">
            <input type="checkbox" name="track_expiry" value="1" @checked(old('track_expiry', $product?->track_expiry)) class="rounded border-line">
            Track expiry dates
        </label>
    </div>
</div>
