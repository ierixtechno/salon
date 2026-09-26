<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $quotation->quotation_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0; color: #4338ca; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .logo { height: 46px; margin-bottom: 6px; }
        .box { margin-top: 22px; }
        .box h4 { margin: 0 0 4px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; }
        .items { margin-top: 24px; }
        .items th { background: #eef2ff; color: #3730a3; text-align: left; padding: 7px 8px; font-size: 10px; }
        .items td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .totals { margin-top: 14px; width: 50%; margin-left: 50%; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { border-top: 1px solid #1f2937; font-weight: bold; font-size: 13px; padding-top: 8px; }
        .status { display: inline-block; border: 2px solid #d97706; color: #d97706; font-weight: bold; padding: 3px 12px; letter-spacing: 2px; text-transform: uppercase; }
        .foot { margin-top: 36px; font-size: 9px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                @if ($supplier['logo'])
                    <img class="logo" src="{{ $supplier['logo'] }}" alt="{{ $supplier['name'] }}">
                @endif
                <h1>{{ $supplier['name'] }}</h1>
                <div class="muted">Quotation</div>
                @if ($supplier['gstin'])
                    <div>GSTIN: {{ $supplier['gstin'] }}</div>
                @endif
                @if ($supplier['state'])
                    <div class="muted">State: {{ $supplier['state'] }}</div>
                @endif
            </td>
            <td class="right">
                <div class="status">{{ $quotation->status }}</div>
                <div style="margin-top:8px"><strong>{{ $quotation->quotation_number }}</strong></div>
                <div class="muted">Date: {{ $quotation->created_at->timezone($timezone)->format('d M Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="box">
        <h4>Prepared for</h4>
        <div><strong>{{ $quotation->tenant->name }}</strong></div>
        @if ($quotation->tenant->billing_state)
            <div class="muted">State: {{ $quotation->tenant->billing_state }}</div>
        @endif
        @if ($quotation->tenant->gstin)
            <div class="muted">GSTIN: {{ $quotation->tenant->gstin }}</div>
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
                <td>
                    {{ $quotation->plan->name }} subscription ({{ $quotation->plan->billing_interval }})
                    @if ($quotation->plan->modules->isNotEmpty())
                        <div class="muted">Modules: {{ $quotation->plan->modules->pluck('name')->implode(', ') }}</div>
                    @endif
                    @if ($quotation->branch_count)
                        <div class="muted">{{ $quotation->branch_count }} {{ \Illuminate\Support\Str::plural('branch', $quotation->branch_count) }}</div>
                    @endif
                </td>
                <td>{{ $supplier['sac'] }}</td>
                <td class="right">&#8377;{{ number_format($quotation->amount + $quotation->discount_amount, 2) }}</td>
            </tr>
            @if ((float) $quotation->discount_amount > 0)
                <tr>
                    <td>Discount ({{ rtrim(rtrim(number_format($quotation->discount_percent, 2), '0'), '.') }}%)</td>
                    <td></td>
                    <td class="right">&minus;&#8377;{{ number_format($quotation->discount_amount, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">&#8377;{{ number_format($quotation->amount, 2) }}</td></tr>
        @if ($quotation->igst_amount > 0)
            <tr><td>IGST ({{ number_format($quotation->gst_rate_percent, 2) }}%)</td><td class="right">&#8377;{{ number_format($quotation->igst_amount, 2) }}</td></tr>
        @else
            <tr><td>CGST ({{ number_format($quotation->gst_rate_percent / 2, 2) }}%)</td><td class="right">&#8377;{{ number_format($quotation->cgst_amount, 2) }}</td></tr>
            <tr><td>SGST ({{ number_format($quotation->gst_rate_percent / 2, 2) }}%)</td><td class="right">&#8377;{{ number_format($quotation->sgst_amount, 2) }}</td></tr>
        @endif
        <tr class="grand"><td>Total payable</td><td class="right">&#8377;{{ number_format($quotation->total_amount, 2) }}</td></tr>
    </table>

    @if ($quotation->notes)
        <div class="box">
            <h4>Notes</h4>
            <div>{{ $quotation->notes }}</div>
        </div>
    @endif

    <div class="box">
        <h4>How to pay</h4>
        <div>Log in at {{ config('app.url') }} to pay online, or pay by UPI{{ config('platform.upi_vpa') ? ' to '.config('platform.upi_vpa') : '' }} quoting {{ $quotation->quotation_number }}. Your account is activated as soon as the payment is received.</div>
    </div>

    <div class="foot">This is a computer-generated quotation and does not require a signature.</div>
</body>
</html>
