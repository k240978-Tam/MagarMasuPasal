<?php

namespace Modules\POS\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Customers\Models\Customer;
use Modules\POS\Events\TerminalStateUpdated;
use Modules\POS\Models\HeldBill;
use Modules\Products\Models\Product;
use Modules\Tenancy\Models\BranchTerminal;
use RuntimeException;

/**
 * Owns the live cart draft for a terminal. Draft state is deliberately NOT a
 * database table — it's ephemeral (cache, keyed by terminal) and reconstructed
 * from a REST fetch or the next broadcast on reload. Only two actions persist
 * anything: Hold snapshots the cart into held_bills; Finalize hands the cart
 * off to SaleService, which is the only place a real sales row gets written.
 */
class CartService
{
    protected function cacheKey(BranchTerminal $terminal): string
    {
        return "pos.terminal.{$terminal->id}.cart";
    }

    public function getState(BranchTerminal $terminal): array
    {
        return Cache::get($this->cacheKey($terminal)) ?? $this->emptyCart($terminal);
    }

    public function startSession(BranchTerminal $terminal, User $cashier): array
    {
        $cart = $this->emptyCart($terminal);
        $cart['status'] = 'active';
        $cart['cashier_id'] = $cashier->id;
        $cart['cashier_name'] = $cashier->name;

        return $this->save($terminal, $cart);
    }

    public function addItem(BranchTerminal $terminal, Product $product, float $quantity): array
    {
        $cart = $this->getState($terminal);
        $key = (string) $product->id;

        if (isset($cart['items'][$key])) {
            $cart['items'][$key]['quantity'] += $quantity;
        } else {
            $cart['items'][$key] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_symbol' => $product->unit->symbol,
                'sell_by_weight' => $product->sell_by_weight,
                'quantity' => $quantity,
                'unit_price' => (float) $product->selling_price,
            ];
        }

        $cart['status'] = 'active';

        return $this->save($terminal, $this->recalculate($cart));
    }

    public function updateItemQuantity(BranchTerminal $terminal, int $productId, float $quantity): array
    {
        $cart = $this->getState($terminal);
        $key = (string) $productId;

        if (! isset($cart['items'][$key])) {
            throw new RuntimeException('That product is not in the cart.');
        }

        if ($quantity <= 0) {
            unset($cart['items'][$key]);
        } else {
            $cart['items'][$key]['quantity'] = $quantity;
        }

        return $this->save($terminal, $this->recalculate($cart));
    }

    public function removeItem(BranchTerminal $terminal, int $productId): array
    {
        $cart = $this->getState($terminal);
        unset($cart['items'][(string) $productId]);

        return $this->save($terminal, $this->recalculate($cart));
    }

    public function setDiscountPercent(BranchTerminal $terminal, float $percent): array
    {
        $cart = $this->getState($terminal);
        $cart['discount_percent'] = max(0, min(100, $percent));

        return $this->save($terminal, $this->recalculate($cart));
    }

    public function setCustomer(BranchTerminal $terminal, ?int $customerId): array
    {
        $cart = $this->getState($terminal);

        if ($customerId) {
            $customer = Customer::withoutTenantScope()->findOrFail($customerId);
            $cart['customer_id'] = $customer->id;
            $cart['customer_name'] = $customer->name;
        } else {
            $cart['customer_id'] = null;
            $cart['customer_name'] = null;
        }

        return $this->save($terminal, $cart);
    }

    public function setStatus(BranchTerminal $terminal, string $status): array
    {
        $cart = $this->getState($terminal);
        $cart['status'] = $status;

        return $this->save($terminal, $cart);
    }

    public function clear(BranchTerminal $terminal): array
    {
        return $this->save($terminal, $this->emptyCart($terminal));
    }

    public function hold(BranchTerminal $terminal): HeldBill
    {
        $cart = $this->getState($terminal);

        if (empty($cart['items'])) {
            throw new RuntimeException('Cannot hold an empty cart.');
        }

        $bill = HeldBill::create([
            'business_id' => $terminal->business_id,
            'branch_id' => $terminal->branch_id,
            'terminal_id' => $terminal->id,
            'cashier_id' => $cart['cashier_id'],
            'cart_snapshot' => $cart,
            'held_at' => now(),
        ]);

        $this->clear($terminal);

        return $bill;
    }

    public function resume(BranchTerminal $terminal, HeldBill $bill): array
    {
        $cart = $bill->cart_snapshot;
        $cart['status'] = 'active';
        $this->save($terminal, $cart);

        $bill->delete();

        return $cart;
    }

    protected function emptyCart(BranchTerminal $terminal): array
    {
        return [
            'status' => 'idle',
            'terminal_id' => $terminal->id,
            'terminal_public_id' => $terminal->public_id,
            'branch_id' => $terminal->branch_id,
            'cashier_id' => null,
            'cashier_name' => null,
            'customer_id' => null,
            'customer_name' => null,
            'items' => [],
            'discount_percent' => 0,
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    protected function recalculate(array $cart): array
    {
        $subtotal = 0;

        foreach ($cart['items'] as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }

        $discountAmount = round($subtotal * (($cart['discount_percent'] ?? 0) / 100), 2);

        $cart['subtotal'] = round($subtotal, 2);
        $cart['discount_amount'] = $discountAmount;
        $cart['tax_amount'] = 0;
        $cart['total_amount'] = round($subtotal - $discountAmount, 2);

        return $cart;
    }

    protected function save(BranchTerminal $terminal, array $cart): array
    {
        $cart['updated_at'] = now()->toIso8601String();

        Cache::put($this->cacheKey($terminal), $cart, now()->addHours(12));

        TerminalStateUpdated::dispatch($terminal->public_id, $cart);

        return $cart;
    }
}
