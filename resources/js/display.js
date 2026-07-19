import './bootstrap';
import Alpine from 'alpinejs';

const PROMOS = [
    'Fresh meat, cut daily — Halchowk, Kathmandu.',
    'Ask about our credit accounts for regular customers.',
    'Thank you for shopping with Magar Masu Pasal!',
];

window.displayApp = function (config) {
    return {
        cart: { items: {}, status: 'idle' },
        clock: '',
        promoIndex: 0,

        get promo() {
            return PROMOS[this.promoIndex];
        },

        init() {
            this.updateClock();
            setInterval(() => this.updateClock(), 1000);
            setInterval(() => { this.promoIndex = (this.promoIndex + 1) % PROMOS.length; }, 6000);

            window.Echo.channel(`terminal.${config.terminalPublicId}`).listen('.terminal.state.updated', (state) => {
                this.cart = state;
            });
        },

        updateClock() {
            this.clock = new Date().toLocaleString('en-US', {
                weekday: 'short', day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit',
            });
        },

        hasItems() {
            return Object.keys(this.cart.items || {}).length > 0;
        },

        cartItems() {
            return Object.values(this.cart.items || {});
        },
    };
};

window.Alpine = Alpine;
Alpine.start();
