import './bootstrap';
import Alpine from 'alpinejs';

const CATEGORY_EMOJI = {
    Chicken: '🐔',
    Mutton: '🐐',
    Buff: '🐄',
    Vegetables: '🥬',
};

function api(base, csrf) {
    return async function (method, path, body) {
        const res = await fetch(base + path, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            const message = data.message || Object.values(data.errors || {}).flat()[0] || 'Something went wrong.';
            throw new Error(message);
        }

        return data;
    };
}

window.posApp = function (config) {
    const base = `/pos/terminals/${config.terminalPublicId}`;
    const call = api(base, config.csrf);

    return {
        cart: { items: {}, status: 'idle' },
        products: (config.products || []).map((p) => ({ ...p, emoji: CATEGORY_EMOJI[p.category_name] || '🛒' })),
        customers: config.customers || [],
        gateways: config.gateways || [],
        activeCategory: 'all',
        search: '',
        discountInput: 0,
        showWeightModal: false,
        weightProduct: null,
        weightValue: 1,
        showCustomerModal: false,
        showHeldBills: false,
        showPaymentModal: false,
        heldBills: [],
        error: null,
        lastSale: null,

        async init() {
            this.cart = await call('GET', '/cart');

            if (this.cart.status === 'idle') {
                this.cart = await call('POST', '/cart/start');
            }

            window.Echo.channel(`terminal.${config.terminalPublicId}`).listen('.terminal.state.updated', (state) => {
                this.cart = state;
                this.discountInput = state.discount_percent || 0;
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'F1') { e.preventDefault(); this.$refs?.search?.focus?.(); }
                if (e.key === 'F5') { e.preventDefault(); this.holdBill(); }
                if (e.key === 'F9') { e.preventDefault(); if (this.hasItems()) this.showPaymentModal = true; }
            });
        },

        hasItems() {
            return Object.keys(this.cart.items || {}).length > 0;
        },

        cartItems() {
            return Object.values(this.cart.items || {});
        },

        filteredProducts() {
            return this.products.filter((p) => {
                const matchesCategory = this.activeCategory === 'all' || p.category_id === this.activeCategory;
                const matchesSearch = !this.search || p.name.toLowerCase().includes(this.search.toLowerCase());

                return matchesCategory && matchesSearch;
            });
        },

        addProduct(product) {
            if (product.sell_by_weight) {
                this.weightProduct = product;
                this.weightValue = 0.5;
                this.showWeightModal = true;

                return;
            }

            this.runOrReport(() => call('POST', '/cart/items', { product_id: product.id, quantity: 1 }));
        },

        confirmWeight() {
            const qty = Number(this.weightValue);

            if (!qty || qty <= 0) return;

            this.runOrReport(() => call('POST', '/cart/items', { product_id: this.weightProduct.id, quantity: qty }));
            this.showWeightModal = false;
        },

        adjustQty(item, delta) {
            const next = Math.max(0, Math.round((item.quantity + delta) * 1000) / 1000);
            this.runOrReport(() => call('PATCH', `/cart/items/${item.product_id}`, { quantity: next }));
        },

        removeItem(productId) {
            this.runOrReport(() => call('DELETE', `/cart/items/${productId}`));
        },

        applyDiscount() {
            this.runOrReport(() => call('PATCH', '/cart/discount', { percent: this.discountInput || 0 }));
        },

        selectCustomer(customer) {
            this.showCustomerModal = false;
            this.runOrReport(() => call('PATCH', '/cart/customer', { customer_id: customer ? customer.id : null }));
        },

        async holdBill() {
            if (!this.hasItems()) return;
            await this.runOrReport(() => call('POST', '/cart/hold'));
        },

        async loadHeldBills() {
            this.heldBills = await call('GET', '/held-bills');
        },

        async resumeBill(publicId) {
            this.showHeldBills = false;
            await this.runOrReport(() => call('POST', `/cart/resume/${publicId}`));
        },

        gatewayLabel(key) {
            return { cash: '💵 Cash', manual_qr: '📱 QR' }[key] || key;
        },

        async charge(gatewayKey) {
            await this.finalizeSale('retail', [{ gateway_key: gatewayKey, amount: this.cart.total_amount }]);
        },

        async chargeCredit() {
            await this.finalizeSale('credit', []);
        },

        async finalizeSale(saleType, payments) {
            this.error = null;

            try {
                const result = await call('POST', '/cart/checkout', { sale_type: saleType, payments });
                this.showPaymentModal = false;
                this.lastSale = result.sale;
                setTimeout(() => { this.lastSale = null; }, 4000);
                // Give the Customer Display a few seconds to show "Payment
                // successful — Thank you!" before the cart resets to idle.
                await new Promise((resolve) => setTimeout(resolve, 3000));
                await call('POST', '/cart/complete');
                this.discountInput = 0;
            } catch (e) {
                this.error = e.message;
            }
        },

        async runOrReport(fn) {
            try {
                this.cart = await fn();
            } catch (e) {
                this.error = e.message;
            }
        },
    };
};

window.Alpine = Alpine;
Alpine.start();
