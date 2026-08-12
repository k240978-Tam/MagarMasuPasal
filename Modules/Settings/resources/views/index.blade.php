<x-layouts.app title="Settings">
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">Settings</h1>
            <p class="mt-1 text-sm text-ink-soft">Business-wide configuration.</p>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-line bg-surface px-4 py-2 text-sm text-ink">{{ session('status') }}</div>
        @endif

        <div class="rounded-xl border border-line bg-surface p-4">
            <h2 class="text-sm font-semibold">Business Tax Details</h2>
            <p class="mt-1 text-xs text-ink-soft">
                IRD requires the registered name and PAN/VAT number to appear on every tax invoice.
                Leave the PAN blank until the business is registered — invoices then print without it.
            </p>

            <form method="POST" action="{{ route('settings.business-profile.update') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <div>
                    <label for="legal_name" class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Registered Name</label>
                    <input type="text" id="legal_name" name="legal_name" value="{{ old('legal_name', $business->legal_name) }}"
                        class="mt-1 w-64 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm" placeholder="As registered with IRD">
                    @error('legal_name')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="pan_vat_number" class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">PAN / VAT No.</label>
                    <input type="text" id="pan_vat_number" name="pan_vat_number" value="{{ old('pan_vat_number', $business->pan_vat_number) }}"
                        class="mt-1 w-48 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm" placeholder="e.g. 301234567">
                    @error('pan_vat_number')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Save</button>
            </form>
        </div>

        <div class="rounded-xl border border-line bg-surface p-4">
            <h2 class="text-sm font-semibold">Tax Rules</h2>
            <p class="mt-1 text-xs text-ink-soft">
                Products only get taxed when explicitly assigned one of these rules — untagged
                products (e.g. fresh meat, vegetables) stay tax-exempt.
            </p>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-ink-soft">
                            <th class="pb-2">Name</th>
                            <th class="pb-2 text-right">Rate</th>
                            <th class="pb-2 text-right">Status</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($taxRules as $rule)
                            <tr class="border-t border-line">
                                <td class="py-2">{{ $rule->name }}</td>
                                <td class="py-2 text-right tabular-nums">{{ number_format($rule->rate, 2) }}%</td>
                                <td class="py-2 text-right">
                                    <span class="rounded-full border px-2 py-0.5 text-xs font-medium {{ $rule->is_active ? 'border-success text-success' : 'border-line text-ink-soft' }}">
                                        {{ $rule->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('settings.tax-rules.toggle', $rule) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-xs font-medium text-info hover:underline">{{ $rule->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('settings.tax-rules.destroy', $rule) }}" class="inline" onsubmit="return confirm('Delete this tax rule?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="ml-2 text-xs font-medium text-critical hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-ink-soft">No tax rules yet — all products are tax-exempt.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('settings.tax-rules.store') }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-line pt-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Name</label>
                    <input type="text" name="name" required placeholder="e.g. VAT" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Rate %</label>
                    <input type="number" name="rate" step="0.01" min="0" max="100" required placeholder="13.00" class="mt-1 w-28 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                </div>
                <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Add Tax Rule</button>
            </form>
        </div>

        <div class="rounded-xl border border-line bg-surface p-4">
            <h2 class="text-sm font-semibold">Receipt Template</h2>
            <p class="mt-1 text-xs text-ink-soft">Customize what prints on every receipt, without touching code.</p>

            <form method="POST" action="{{ route('settings.receipt-template.update') }}" class="mt-3 flex flex-col gap-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Header Note</label>
                    <input type="text" name="header_note" value="{{ old('header_note', $receiptTemplate['header_note']) }}" placeholder="e.g. PAN: 123456789" class="mt-1 w-full max-w-md rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Footer Text</label>
                    <input type="text" name="footer_text" value="{{ old('footer_text', $receiptTemplate['footer_text']) }}" class="mt-1 w-full max-w-md rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                </div>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="show_qr" value="1" {{ $receiptTemplate['show_qr'] ? 'checked' : '' }} class="rounded border-line">
                    Show QR code on receipt
                </label>
                <button type="submit" class="w-fit rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Save Receipt Template</button>
            </form>
        </div>

        <div class="rounded-xl border border-line bg-surface p-4">
            <h2 class="text-sm font-semibold">Feature Toggles</h2>
            <p class="mt-1 text-xs text-ink-soft">Turn off optional features this business doesn't need. Everything defaults to on.</p>

            <form method="POST" action="{{ route('settings.feature-toggles.update') }}" class="mt-3 flex flex-col gap-2">
                @csrf
                @method('PUT')
                @foreach ($featureFlags as $key => $label)
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="flags[]" value="{{ $key }}" {{ ($enabledFlags[$key] ?? true) ? 'checked' : '' }} class="rounded border-line">
                        {{ $label }}
                    </label>
                @endforeach
                <button type="submit" class="mt-2 w-fit rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Save Feature Toggles</button>
            </form>
        </div>
    </div>
</x-layouts.app>
