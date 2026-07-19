<?php

namespace Modules\CashRegister\Services;

use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\CashRegister\Models\CashMovement;
use Modules\CashRegister\Models\CashSession;
use Modules\Sales\Models\Sale;

class CashSessionService
{
    public function open(int $businessId, int $branchId, int $terminalId, int $userId, float $openingCash): CashSession
    {
        $existing = CashSession::withoutTenantScope()
            ->where('terminal_id', $terminalId)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            throw new LogicException('This terminal already has an open cash session.');
        }

        return CashSession::create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'terminal_id' => $terminalId,
            'opened_by' => $userId,
            'opening_cash' => $openingCash,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    public function recordMovement(CashSession $session, string $type, float $amount, ?string $reason, int $userId): CashMovement
    {
        return CashMovement::create([
            'business_id' => $session->business_id,
            'cash_session_id' => $session->id,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'created_by' => $userId,
        ]);
    }

    public function close(CashSession $session, float $countedCash, int $userId): CashSession
    {
        return DB::transaction(function () use ($session, $countedCash, $userId) {
            $expected = $this->expectedCash($session);
            $variance = round($countedCash - $expected, 2);

            $session->update([
                'closed_by' => $userId,
                'closing_cash_expected' => $expected,
                'closing_cash_counted' => $countedCash,
                'variance' => $variance,
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            return $session->fresh();
        });
    }

    /**
     * opening + cash sales during the session window + paid-in - paid-out.
     */
    public function expectedCash(CashSession $session): float
    {
        $cashSales = Sale::withoutTenantScope()
            ->where('sales.business_id', $session->business_id)
            ->where('sales.terminal_id', $session->terminal_id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.completed_at', [$session->opened_at, $session->closed_at ?? now()])
            ->join('sale_payments', 'sale_payments.sale_id', '=', 'sales.id')
            ->join('payment_transactions', 'payment_transactions.id', '=', 'sale_payments.payment_transaction_id')
            ->where('payment_transactions.gateway_key', 'cash')
            ->where('payment_transactions.status', 'captured')
            ->sum('sale_payments.amount');

        $paidIn = $session->movements()->where('type', 'paid_in')->sum('amount');
        $paidOut = $session->movements()->where('type', 'paid_out')->sum('amount');

        return round((float) $session->opening_cash + (float) $cashSales + (float) $paidIn - (float) $paidOut, 2);
    }
}
