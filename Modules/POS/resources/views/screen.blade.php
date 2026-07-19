<x-layouts.pos title="Cashier POS">
    <div
        x-data="posApp({
            terminalPublicId: @js($terminal->public_id),
            gateways: @js($gateways),
            products: @js($products),
            customers: @js($customers),
            csrf: document.querySelector('meta[name=csrf-token]').content,
        })"
        x-init="init()"
        class="flex h-screen flex-col"
    >
        <!-- Top bar -->
        <header class="flex items-center justify-between border-b border-line bg-surface px-4 py-2.5">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent text-sm font-bold text-accent-ink">M</span>
                <div class="leading-tight">
                    <div class="text-sm font-semibold">{{ auth()->user()->business?->name }}</div>
                    <div class="text-xs text-ink-soft">{{ $terminal->branch->name }} · {{ $terminal->name }}</div>
                </div>
            </div>
            <div class="flex items-center gap-4 text-sm text-ink-soft">
                <span>Cashier: {{ auth()->user()->name }}</span>
                <span x-show="cart.status" class="rounded-full bg-surface-2 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide" x-text="cart.status"></span>
                <button type="button" @click="theme = theme === 'dark' ? 'light' : 'dark'" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line hover:bg-surface-2">
                    <span x-show="theme === 'light'">🌙</span><span x-show="theme === 'dark'">☀️</span>
                </button>
                <a href="{{ route('dashboard') }}" class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium hover:bg-surface-2">Exit</a>
            </div>
        </header>

        <div class="grid flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[1fr_360px]">
            <!-- Catalog -->
            <div class="flex flex-col overflow-hidden border-r border-line">
                <div class="flex gap-2 border-b border-line p-3">
                    <div class="flex flex-1 items-center gap-2 rounded-lg border border-line bg-surface-2 px-3 py-2">
                        <span>🔍</span>
                        <input x-model="search" type="text" placeholder="Search or scan barcode…" class="w-full bg-transparent text-sm outline-none" autofocus>
                    </div>
                </div>
                <div class="flex gap-2 overflow-x-auto border-b border-line p-3">
                    <button type="button" @click="activeCategory = 'all'" :class="activeCategory === 'all' ? 'bg-ink text-bg' : 'border border-line text-ink-soft'" class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold">All</button>
                    @foreach ($categories as $category)
                        <button type="button" @click="activeCategory = {{ $category->id }}" :class="activeCategory === {{ $category->id }} ? 'bg-ink text-bg' : 'border border-line text-ink-soft'" class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold">{{ $category->name }}</button>
                    @endforeach
                </div>
                <div class="grid flex-1 auto-rows-min grid-cols-2 gap-3 overflow-y-auto p-4 sm:grid-cols-3 xl:grid-cols-4">
                    <template x-for="product in filteredProducts()" :key="product.id">
                        <button type="button" @click="addProduct(product)" class="flex flex-col gap-2 rounded-xl border border-line bg-surface p-3 text-left hover:border-accent hover:shadow-sm">
                            <div class="flex h-11 items-center justify-center rounded-lg bg-accent-soft text-xl" x-text="product.emoji"></div>
                            <div class="text-xs font-semibold leading-tight" x-text="product.name"></div>
                            <div class="text-xs text-ink-soft tabular-nums" x-text="'Rs ' + product.selling_price + ' / ' + product.unit_symbol"></div>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Cart -->
            <div class="flex flex-col overflow-hidden bg-surface-2">
                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <b class="text-sm">Current Sale</b>
                    <div class="flex gap-2 text-xs font-semibold">
                        <button type="button" @click="holdBill()" :disabled="!hasItems()" class="text-info disabled:opacity-40">Hold</button>
                        <button type="button" @click="showHeldBills = true; loadHeldBills()" class="text-info">Held (<span x-text="heldBills.length"></span>)</button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-3">
                    <template x-if="!hasItems()">
                        <p class="mt-8 text-center text-sm text-ink-soft">Cart is empty — tap a product to begin.</p>
                    </template>
                    <div class="flex flex-col gap-2">
                        <template x-for="item in cartItems()" :key="item.product_id">
                            <div class="rounded-lg border border-line bg-surface p-2.5">
                                <div class="flex items-center justify-between text-sm font-semibold">
                                    <span x-text="item.name"></span>
                                    <span class="tabular-nums" x-text="'Rs ' + (item.quantity * item.unit_price).toFixed(2)"></span>
                                </div>
                                <div class="mt-1 flex items-center justify-between text-xs text-ink-soft">
                                    <span class="tabular-nums" x-text="item.quantity + ' ' + item.unit_symbol + ' × Rs ' + item.unit_price"></span>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="rounded border border-line px-1.5" @click="adjustQty(item, item.sell_by_weight ? -0.1 : -1)">−</button>
                                        <button type="button" class="rounded border border-line px-1.5" @click="adjustQty(item, item.sell_by_weight ? 0.1 : 1)">+</button>
                                        <button type="button" class="text-critical" @click="removeItem(item.product_id)">✕</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="border-t border-line px-4 py-3">
                    <button type="button" @click="showCustomerModal = true" class="mb-3 flex w-full items-center justify-between rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                        <span x-text="cart.customer_name || 'Walk-in customer'"></span>
                        <span class="text-info">Change</span>
                    </button>

                    <div class="mb-3 flex items-center justify-between text-sm">
                        <span class="text-ink-soft">Discount</span>
                        <div class="flex items-center gap-1">
                            <input type="number" min="0" max="100" x-model.number="discountInput" @change="applyDiscount()" class="w-16 rounded border border-line bg-surface px-2 py-1 text-right text-sm tabular-nums">
                            <span class="text-ink-soft">%</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1 border-t border-dashed border-line-strong pt-2 text-sm">
                        <div class="flex justify-between text-ink-soft"><span>Subtotal</span><span class="tabular-nums" x-text="'Rs ' + (cart.subtotal || 0).toFixed(2)"></span></div>
                        <div class="flex justify-between text-ink-soft"><span>Discount</span><span class="tabular-nums" x-text="'− Rs ' + (cart.discount_amount || 0).toFixed(2)"></span></div>
                        <div x-show="cart.tax_amount > 0" class="flex justify-between text-ink-soft"><span>Tax</span><span class="tabular-nums" x-text="'+ Rs ' + (cart.tax_amount || 0).toFixed(2)"></span></div>
                        <div class="flex justify-between pt-1 text-lg font-bold"><span>Total</span><span class="tabular-nums" x-text="'Rs ' + (cart.total_amount || 0).toFixed(2)"></span></div>
                    </div>

                    <button type="button" @click="showPaymentModal = true" :disabled="!hasItems()"
                        class="mt-3 w-full rounded-lg bg-accent py-3 text-sm font-bold text-accent-ink disabled:opacity-40">
                        Charge Rs <span x-text="(cart.total_amount || 0).toFixed(2)"></span>
                    </button>
                </div>
            </div>
        </div>

        <footer class="flex gap-4 border-t border-line bg-surface px-4 py-2 text-xs text-ink-soft">
            <span><kbd class="rounded border border-line bg-surface-2 px-1">F1</kbd> Search</span>
            <span><kbd class="rounded border border-line bg-surface-2 px-1">F5</kbd> Hold</span>
            <span><kbd class="rounded border border-line bg-surface-2 px-1">F9</kbd> Charge</span>
        </footer>

        <!-- Weight/quantity modal -->
        <div x-show="showWeightModal" x-cloak class="fixed inset-0 z-30 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-xs rounded-xl bg-surface p-5 shadow-lg" @click.outside="showWeightModal = false">
                <h3 class="text-sm font-semibold" x-text="weightProduct?.name"></h3>
                <p class="mt-1 text-xs text-ink-soft" x-text="'Enter quantity in ' + weightProduct?.unit_symbol"></p>
                <input type="number" step="0.001" min="0.001" x-model.number="weightValue" @keydown.enter="confirmWeight()"
                    class="mt-3 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-lg tabular-nums" autofocus>
                <div class="mt-4 flex gap-2">
                    <button type="button" @click="showWeightModal = false" class="flex-1 rounded-lg border border-line py-2 text-sm">Cancel</button>
                    <button type="button" @click="confirmWeight()" class="flex-1 rounded-lg bg-accent py-2 text-sm font-semibold text-accent-ink">Add</button>
                </div>
            </div>
        </div>

        <!-- Customer modal -->
        <div x-show="showCustomerModal" x-cloak class="fixed inset-0 z-30 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-xl bg-surface p-5 shadow-lg" @click.outside="showCustomerModal = false">
                <h3 class="text-sm font-semibold">Select customer</h3>
                <div class="mt-3 flex max-h-72 flex-col gap-1 overflow-y-auto">
                    <button type="button" @click="selectCustomer(null)" class="rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-2">Walk-in customer</button>
                    <template x-for="customer in customers" :key="customer.id">
                        <button type="button" @click="selectCustomer(customer)" class="flex items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-2">
                            <span x-text="customer.name"></span>
                            <span class="text-xs text-ink-soft" x-text="customer.group_name"></span>
                        </button>
                    </template>
                </div>
                <button type="button" @click="showCustomerModal = false" class="mt-3 w-full rounded-lg border border-line py-2 text-sm">Close</button>
            </div>
        </div>

        <!-- Held bills modal -->
        <div x-show="showHeldBills" x-cloak class="fixed inset-0 z-30 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-xl bg-surface p-5 shadow-lg" @click.outside="showHeldBills = false">
                <h3 class="text-sm font-semibold">Held bills</h3>
                <template x-if="heldBills.length === 0"><p class="mt-3 text-sm text-ink-soft">No held bills.</p></template>
                <div class="mt-3 flex max-h-72 flex-col gap-1 overflow-y-auto">
                    <template x-for="bill in heldBills" :key="bill.public_id">
                        <button type="button" @click="resumeBill(bill.public_id)" class="flex items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-2">
                            <span x-text="Object.keys(bill.cart_snapshot.items || {}).length + ' item(s)'"></span>
                            <span class="tabular-nums" x-text="'Rs ' + bill.cart_snapshot.total_amount"></span>
                        </button>
                    </template>
                </div>
                <button type="button" @click="showHeldBills = false" class="mt-3 w-full rounded-lg border border-line py-2 text-sm">Close</button>
            </div>
        </div>

        <!-- Payment modal -->
        <div x-show="showPaymentModal" x-cloak class="fixed inset-0 z-30 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-xl bg-surface p-5 shadow-lg" @click.outside="showPaymentModal = false">
                <h3 class="text-sm font-semibold">Charge Rs <span x-text="(cart.total_amount || 0).toFixed(2)"></span></h3>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <template x-for="gateway in gateways" :key="gateway">
                        <button type="button" @click="charge(gateway)" class="rounded-lg border border-line py-3 text-sm font-semibold hover:border-accent" x-text="gatewayLabel(gateway)"></button>
                    </template>
                    <button type="button" x-show="cart.customer_id" @click="chargeCredit()" class="col-span-2 rounded-lg border border-line py-3 text-sm font-semibold hover:border-accent">Bill to Account (Credit)</button>
                </div>
                <p x-show="error" x-text="error" class="mt-3 text-xs text-critical"></p>
                <button type="button" @click="showPaymentModal = false" class="mt-3 w-full rounded-lg border border-line py-2 text-sm">Cancel</button>
            </div>
        </div>

        <!-- Success toast -->
        <div x-show="lastSale" x-cloak x-transition class="fixed bottom-6 right-6 z-40 rounded-xl border border-success/30 bg-surface p-4 shadow-lg">
            <div class="text-sm font-semibold text-success">✅ Sale completed</div>
            <div class="text-xs text-ink-soft" x-text="lastSale?.invoice_no + ' · Rs ' + lastSale?.total_amount"></div>
            <a :href="'/sales/' + lastSale?.public_id + '/receipt'" target="_blank" class="mt-1 inline-block text-xs font-semibold text-info">Print receipt</a>
        </div>
    </div>
</x-layouts.pos>
