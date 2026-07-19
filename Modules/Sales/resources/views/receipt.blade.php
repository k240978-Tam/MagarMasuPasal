<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1c231f; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .muted { color: #5c6660; }
        table { width: 100%; border-collapse: collapse; }
        .line { border-top: 1px dashed #999; margin: 6px 0; }
        .totals td { padding: 1px 0; }
        .item-name { padding-top: 4px; }
    </style>
</head>
<body>
    <div class="center">
        <div class="bold" style="font-size: 14px;">{{ $business->name }}</div>
        @if ($sale->branch)
            <div class="muted">{{ $sale->branch->name }}</div>
        @endif
        <div class="muted">{{ $sale->completed_at->format('d M Y, h:i A') }}</div>
    </div>

    <div class="line"></div>

    <table>
        <tr><td>Invoice</td><td class="right bold">{{ $sale->invoice_no }}</td></tr>
        <tr><td>Cashier</td><td class="right">{{ $sale->cashier->name }}</td></tr>
        <tr><td>Customer</td><td class="right">{{ $sale->customer->name ?? 'Walk-in' }}</td></tr>
    </table>

    <div class="line"></div>

    <table>
        @foreach ($sale->items as $item)
            <tr>
                <td colspan="2" class="item-name bold">{{ $item->product->name }}</td>
            </tr>
            <tr>
                <td class="muted">{{ number_format($item->quantity, 3) }} {{ $item->product->unit->symbol }} × Rs {{ number_format($item->unit_price, 2) }}</td>
                <td class="right">Rs {{ number_format($item->line_total, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="line"></div>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">Rs {{ number_format($sale->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="right">− Rs {{ number_format($sale->discount_amount, 2) }}</td></tr>
        <tr><td>Tax</td><td class="right">Rs {{ number_format($sale->tax_amount, 2) }}</td></tr>
        <tr><td class="bold" style="font-size: 13px;">Total</td><td class="right bold" style="font-size: 13px;">Rs {{ number_format($sale->total_amount, 2) }}</td></tr>
    </table>

    <div class="line"></div>

    <table>
        @foreach ($sale->payments as $payment)
            <tr>
                <td>{{ ucfirst(str_replace('_', ' ', $payment->transaction->gateway_key)) }}</td>
                <td class="right">Rs {{ number_format($payment->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="center" style="margin-top: 12px;">
        <img src="data:image/svg+xml;base64,{{ $qrSvg }}" width="90" height="90">
        <div class="muted" style="margin-top: 4px;">{{ $sale->public_id }}</div>
    </div>

    <div class="center bold" style="margin-top: 10px;">Thank you for shopping with us!</div>
</body>
</html>
