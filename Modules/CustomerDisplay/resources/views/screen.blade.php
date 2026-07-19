<x-layouts.display title="Customer Display">
    <style>
        .cd-bg {
            background: radial-gradient(120% 140% at 50% -10%, #1c2b22 0%, #0d130f 60%, #090d0a 100%);
            color: #f4f1e8;
        }
    </style>

    <div
        x-data="displayApp({ terminalPublicId: @js($terminal->public_id) })"
        x-init="init()"
        class="cd-bg flex h-screen flex-col items-center justify-center px-10 py-8"
    >
        <!-- Idle: business branding + rotating promos -->
        <template x-if="!hasItems()">
            <div class="flex flex-col items-center gap-6 text-center" x-transition.opacity.duration.700ms>
                <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-[#E39A2A] text-4xl font-bold text-[#241300]">
                    {{ substr($business->name, 0, 1) }}
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $business->name }}</div>
                    <div class="mt-2 text-sm tracking-wide text-[#9FAE9F]" x-text="clock"></div>
                </div>
                <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 px-8 py-5 text-lg text-[#E8E4D8]" x-text="promo"></div>
            </div>
        </template>

        <!-- Active cart -->
        <template x-if="hasItems()">
            <div class="flex w-full max-w-2xl flex-col gap-6">
                <div class="text-center">
                    <div class="text-lg font-bold">{{ $business->name }}</div>
                    <div class="mt-1 flex justify-center gap-3 text-xs text-[#9FAE9F]">
                        <span x-text="clock"></span>
                        <span x-show="cart.cashier_name" x-text="'Cashier: ' + cart.cashier_name"></span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-y border-white/10 py-5">
                    <template x-for="item in cartItems()" :key="item.product_id">
                        <div class="flex items-center justify-between" x-transition>
                            <div>
                                <div class="font-semibold" x-text="item.name"></div>
                                <div class="text-xs text-[#9FAE9F] tabular-nums" x-text="item.quantity + ' ' + item.unit_symbol + ' @ Rs ' + item.unit_price"></div>
                            </div>
                            <div class="tabular-nums font-semibold" x-text="'Rs ' + (item.quantity * item.unit_price).toFixed(2)"></div>
                        </div>
                    </template>
                </div>

                <div class="flex justify-between text-sm text-[#9FAE9F]">
                    <span>Subtotal</span><span class="tabular-nums" x-text="'Rs ' + (cart.subtotal || 0).toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-sm text-[#9FAE9F]">
                    <span>Discount</span><span class="tabular-nums" x-text="'Rs ' + (cart.discount_amount || 0).toFixed(2)"></span>
                </div>

                <div class="rounded-2xl border border-[#E39A2A]/40 bg-[#E39A2A]/10 py-5 text-center">
                    <div class="text-xs uppercase tracking-widest text-[#E39A2A]">Grand Total</div>
                    <div class="mt-1 text-4xl font-bold tabular-nums" x-text="'Rs ' + (cart.total_amount || 0).toFixed(2)"></div>
                </div>

                <div class="text-center text-sm text-[#9FAE9F]">
                    <template x-if="cart.status === 'awaiting_payment'"><span>⏳ Waiting for payment…</span></template>
                    <template x-if="cart.status === 'payment_success'"><span class="text-[#7CD9A5]">✅ Payment successful — Thank you!</span></template>
                    <template x-if="cart.status === 'active'"><span>Adding items…</span></template>
                </div>
            </div>
        </template>
    </div>
</x-layouts.display>
