<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0; color: #4338ca; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .box { margin-top: 22px; }
        .box h4 { margin: 0 0 4px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; }
        .items { margin-top: 24px; }
        .items th { background: #eef2ff; color: #3730a3; text-align: left; padding: 7px 8px; font-size: 10px; }
        .items td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .totals { margin-top: 14px; width: 50%; margin-left: 50%; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { border-top: 1px solid #1f2937; font-weight: bold; font-size: 13px; padding-top: 8px; }
        .paid { display: inline-block; border: 2px solid #16a34a; color: #16a34a; font-weight: bold; padding: 3px 12px; letter-spacing: 2px; }
        .foot { margin-top: 36px; font-size: 9px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <h1>{{ $supplier['name'] }}</h1>
                <div class="muted">Tax Invoice</div>
                @if ($supplier['gstin'])
                    <div>GSTIN: {{ $supplier['gstin'] }}</div>
                @endif
                @if ($supplier['state'])
                    <div class="muted">State: {{ $supplier['state'] }}</div>
                @endif
            </td>
            <td class="right">
                <div class="paid">PAID</div>
                <div style="margin-top:8px"><strong>{{ $invoice->invoice_number }}</strong></div>
                <div class="muted">Date: {{ $invoice->paid_at->timezone($timezone)->format('d M Y, h:i A') }}</div>
                @if ($invoice->quotation_number)
                    <div class="muted">Quotation: {{ $invoice->quotation_number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="box">
        <h4>Billed to</h4>
        <div><strong>{{ $invoice->tenant->name }}</strong></div>
        @if ($invoice->tenant->billing_state)
            <div class="muted">State: {{ $invoice->tenant->billing_state }}</div>
        @endif
        @if ($invoice->tenant->gstin)
            <div class="muted">GSTIN: {{ $invoice->tenant->gstin }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th>SAC</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->plan->name }} subscription ({{ $invoice->plan->billing_interval }})</td>
                <td>{{ $supplier['sac'] }}</td>
                <td class="right">&#8377;{{ number_format($invoice->subtotal + $invoice->discount_amount, 2) }}</td>
            </tr>
            @if ((float) $invoice->discount_amount > 0)
                <tr>
                    <td>Discount ({{ rtrim(rtrim(number_format($invoice->discount_percent, 2), '0'), '.') }}%)</td>
                    <td></td>
                    <td class="right">&minus;&#8377;{{ number_format($invoice->discount_amount, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">&#8377;{{ number_format($invoice->subtotal, 2) }}</td></tr>
        @if ($invoice->igst_amount > 0)
            <tr><td>IGST ({{ number_format($invoice->gst_rate_percent, 2) }}%)</td><td class="right">&#8377;{{ number_format($invoice->igst_amount, 2) }}</td></tr>
        @else
            <tr><td>CGST ({{ number_format($invoice->gst_rate_percent / 2, 2) }}%)</td><td class="right">&#8377;{{ number_format($invoice->cgst_amount, 2) }}</td></tr>
            <tr><td>SGST ({{ number_format($invoice->gst_rate_percent / 2, 2) }}%)</td><td class="right">&#8377;{{ number_format($invoice->sgst_amount, 2) }}</td></tr>
        @endif
        <tr class="grand"><td>Total paid</td><td class="right">&#8377;{{ number_format($invoice->amount, 2) }}</td></tr>
    </table>

    <div class="box">
        <h4>Payment</h4>
        <div>Method: {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</div>
        @if ($invoice->payment_reference)
            <div>Reference: {{ $invoice->payment_reference }}</div>
        @endif
    </div>

    <div class="foot">This is a computer-generated invoice and does not require a signature.</div>
</body>
</html>
