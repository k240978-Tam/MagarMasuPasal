<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Accounting\Models\ChartOfAccount;
use RuntimeException;

/**
 * Seeds each tenant's starting Chart of Accounts and resolves accounts by
 * code for the auto-posting listeners. Every business gets the same system
 * accounts (is_system = true) on creation; a tenant can add its own
 * sub-accounts on top without touching these.
 */
class ChartOfAccountsService
{
    public const CASH = '1000';

    public const BANK = '1010';

    public const ACCOUNTS_RECEIVABLE = '1100';

    public const INVENTORY_ASSET = '1200';

    public const ACCOUNTS_PAYABLE = '2000';

    public const OWNERS_EQUITY = '3000';

    public const RETAINED_EARNINGS = '3900';

    public const SALES_REVENUE = '4000';

    public const COST_OF_GOODS_SOLD = '5000';

    public const GENERAL_EXPENSES = '6000';

    public const CASH_OVER_SHORT = '6100';

    public function seedDefaults(int $businessId): void
    {
        $accounts = [
            [self::CASH, 'Cash', 'asset'],
            [self::BANK, 'Bank / Digital Payments', 'asset'],
            [self::ACCOUNTS_RECEIVABLE, 'Accounts Receivable', 'asset'],
            [self::INVENTORY_ASSET, 'Inventory Asset', 'asset'],
            [self::ACCOUNTS_PAYABLE, 'Accounts Payable', 'liability'],
            [self::OWNERS_EQUITY, "Owner's Equity", 'equity'],
            [self::RETAINED_EARNINGS, 'Retained Earnings', 'equity'],
            [self::SALES_REVENUE, 'Sales Revenue', 'income'],
            [self::COST_OF_GOODS_SOLD, 'Cost of Goods Sold', 'expense'],
            [self::GENERAL_EXPENSES, 'General Expenses', 'expense'],
            [self::CASH_OVER_SHORT, 'Cash Over/Short', 'expense'],
        ];

        foreach ($accounts as [$code, $name, $type]) {
            ChartOfAccount::withoutTenantScope()->updateOrCreate(
                ['business_id' => $businessId, 'code' => $code],
                ['name' => $name, 'type' => $type, 'is_system' => true],
            );
        }
    }

    public function find(int $businessId, string $code): ChartOfAccount
    {
        return Cache::rememberForever(
            "accounting.account.{$businessId}.{$code}",
            function () use ($businessId, $code) {
                $account = ChartOfAccount::withoutTenantScope()
                    ->where('business_id', $businessId)
                    ->where('code', $code)
                    ->first();

                if (! $account) {
                    throw new RuntimeException("Chart of accounts code [{$code}] is not set up for business #{$businessId}.");
                }

                return $account;
            },
        );
    }

    /**
     * Maps a Payment Manager gateway key to the asset account cash actually
     * lands in. Unknown/future gateways fall back to the Bank account until
     * a tenant configures one explicitly (a Settings concern, later phase).
     */
    public function accountCodeForGateway(string $gatewayKey): string
    {
        return match ($gatewayKey) {
            'cash' => self::CASH,
            default => self::BANK,
        };
    }
}
