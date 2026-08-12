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
        .doc-title { font-size: 12px; letter-spacing: 1px; margin-top: 2px; }
        .copy-mark { border: 1px solid #1c231f; padding: 2px 0; margin-top: 4px; font-size: 10px; }
        .cancelled { border: 2px solid #b3261e; color: #b3261e; padding: 3px 0; margin-top: 4px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="center">
        <div class="bold" style="font-size: 14px;">{{ $business->name }}</div>
        @if ($business->legal_name && $business->legal_name !== $business->name)
            <div class="muted">{{ $business->legal_name }}</div>
        @endif
        @if ($sale->branch)
            <div class="muted">{{ $sale->branch->name }}{{ $sale->branch->address ? ', '.$sale->branch->address : '' }}</div>
        @endif
        @if ($sellerPan)
            <div class="bold">PAN: {{ $sellerPan }}</div>
        @endif
        @if (!empty($template['header_note']))
            <div class="muted">{{ $template['header_note'] }}</div>
        @endif

        {{-- The directive requires the document to name itself; an invoice
             that charges VAT is a Tax Invoice, otherwise it is an Invoice. --}}
        <div class="bold doc-title">{{ (float) $sale->tax_amount > 0 ? 'TAX INVOICE' : 'INVOICE' }}</div>

        @if ($sale->cancelled_at)
            <div class="bold cancelled">CANCELLED INVOICE</div>
        @endif

        {{-- Every reprint must be marked as a copy and numbered. --}}
        @if ($copyNumber > 0)
            <div class="bold copy-mark">COPY OF ORIGINAL — Copy No. {{ $copyNumber }}</div>
        @endif
    </div>

    <div class="line"></div>

    <table>
        <tr><td>Invoice No.</td><td class="right bold">{{ $sale->invoice_no }}</td></tr>
        <tr><td>Date (BS)</td><td class="right bold">{{ $invoiceDateBs }}</td></tr>
        <tr><td>Date (AD)</td><td class="right">{{ $sale->completed_at->format('Y-m-d h:i A') }}</td></tr>
        <tr><td>Fiscal Year</td><td class="right">{{ $sale->fiscal_year }}</td></tr>
        <tr><td>Cashier</td><td class="right">{{ $sale->cashier->name }}</td></tr>
    </table>

    <div class="line"></div>

    <table>
        <tr><td>Buyer</td><td class="right">{{ $sale->buyer_name ?? $sale->customer->name ?? 'Cash Sale' }}</td></tr>
        @if ($buyerPan)
            <tr><td>Buyer PAN</td><td class="right">{{ $buyerPan }}</td></tr>
        @endif
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
        @if ((float) $sale->tax_amount > 0)
            <tr><td>Taxable Amount</td><td class="right">Rs {{ number_format($taxableAmount, 2) }}</td></tr>
            <tr><td>VAT</td><td class="right">Rs {{ number_format($sale->tax_amount, 2) }}</td></tr>
        @else
            <tr><td>VAT</td><td class="right">Rs 0.00</td></tr>
        @endif
        <tr><td class="bold" style="font-size: 13px;">Total</td><td class="right bold" style="font-size: 13px;">Rs {{ number_format($sale->total_amount, 2) }}</td></tr>
    </table>

    <div class="muted" style="margin-top: 4px;">In words: {{ $amountInWords }}</div>

    <div class="line"></div>

    <table>
        @foreach ($sale->payments as $payment)
            <tr>
                <td>{{ ucfirst(str_replace('_', ' ', $payment->transaction->gateway_key)) }}</td>
                <td class="right">Rs {{ number_format($payment->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    @if ($qrSvg)
        <div class="center" style="margin-top: 12px;">
            <img src="data:image/svg+xml;base64,{{ $qrSvg }}" width="90" height="90">
            <div class="muted" style="margin-top: 4px;">{{ $sale->public_id }}</div>
        </div>
    @endif

    <div class="center bold" style="margin-top: 10px;">{{ $template['footer_text'] }}</div>
    <div class="center muted" style="margin-top: 6px; font-size: 9px;">
        Printed {{ now()->format('Y-m-d h:i A') }} · This invoice is computer generated.
    </div>
</body>
</html>
