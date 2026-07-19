<?php

namespace Modules\Sales\DTOs;

/**
 * @param  array<int, array{product_id: int, quantity: float, unit_price: float, discount_amount?: float, tax_amount?: float}>  $items
 * @param  array<int, array{gateway_key: string, amount: float}>  $payments
 */
readonly class FinalizeSaleDTO
{
    public function __construct(
        public int $businessId,
        public int $branchId,
        public int $terminalId,
        public int $cashierId,
        public array $items,
        public array $payments,
        public ?int $customerId = null,
        public string $saleType = 'retail',
        public ?string $notes = null,
    ) {}
}
