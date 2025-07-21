{{--
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Receipt</title>
    <style>
        @media print {

            body,
            html {
                width: 80mm;
                margin: 0;
                padding: 0;
            }

            .receipt {
                width: 80mm;
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }
        }

        body,
        html {
            width: 80mm;
            margin: 0 auto;
            padding: 0;
            background: #fff;
        }

        .receipt {
            width: 70mm;
            margin: 0;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 12px;
            color: #000;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold !important;
        }

        .shop-logo {
            font-size: 20px;
            margin-bottom: 2px;
        }

        .main-label {
            font-size: 14px;
        }

        .divider {
            border-top: 2px dashed #000;
            margin: 8px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            font-size: 13px;
            padding: 2px 0;
        }

        th {
            border-bottom: 1px solid #000;
        }

        td {
            color: #000;
        }

        .totals {
            font-size: 14px;
            font-weight: bold;
        }

        .total-label {
            text-align: left;
        }

        .total-value {
            text-align: right;
            width: 70px;
        }

        .invoice-summary {
            font-size: 14px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="receipt">
        <div class="center shop-logo bold">AMZ Traders</div>
        <div class="center main-label">Main Branch</div>

        <div class="divider"></div>
        <table>
            <tr>
                <td class="bold">Invoice#</td>
                <td>{{ $sale->id }}</td>
            </tr>
            <tr>
                <td class="bold">Date</td>
                <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y, H:i') }}</td>
            </tr>
            <tr>
                <td class="bold">Sold By</td>
                <td>{{ $sale->user->name ?? '-' }}</td>
            </tr>
            @if($sale->vendor)
            <tr>
                <td class="bold">Vendor</td>
                <td>{{ $sale->vendor->name }}</td>
            </tr>
            <tr>
                <td class="bold">Mobile</td>
                <td>{{ $sale->vendor->mobile_no ?? '-' }}</td>
            </tr>
            @elseif($sale->customer_name)
            <tr>
                <td class="bold">Customer</td>
                <td>{{ $sale->customer_name }}</td>
            </tr>
            <tr>
                <td class="bold">Mobile</td>
                <td>+{{ $sale->customer_mobile ?? '-' }}</td>
            </tr>
            @endif
        </table>
        <div class="divider"></div>
        <table>
            <thead>
                <tr>
                    <th style="text-align:left;">Item</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                <tr>
                    <td style="max-width:38mm;white-space:normal;word-break:break-word;" class="bold">{{
                        $item->batch->accessory->name ?? '-' }}</td>
                    <td style="text-align:right;" class="bold">{{ $item->quantity }}</td>
                    <td style="text-align:right;" class="bold">{{ number_format($item->price_per_unit,0) }}</td>
                    <td style="text-align:right;" class="bold">{{ number_format($item->subtotal,0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="divider"></div>
        <table>
            <tr>
                <td class="totals total-label" colspan="3">TOTAL</td>
                <td class="totals total-value">Rs. {{ number_format($sale->total_amount) }}</td>
            </tr>
        </table>
        <div class="divider"></div>
        <div class="center bold" style="font-size:13px;">
            Thank you for shopping!<br>
            <span style="font-size:10px;">Powered by AMZ Traders POS</span>
        </div>
        <div class="no-print center" style="margin-top:10px;">
            <button onclick="window.print()" style="padding:5px 16px;font-size:13px;">Print</button>
        </div>
    </div>
</body>

</html> --}}

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Receipt</title>
    <style>
        @media print {

            body,
            html {
                width: 80mm;
                margin: 0;
                padding: 0;
            }

            .receipt {
                width: 80mm;
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }
        }

        body,
        html {
            width: 80mm;
            margin: 0 auto;
            padding: 0;
            background: #fff;
        }

        .receipt {
            width: 70mm;
            margin: 0;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 12px;
            color: #000;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold !important;
        }

        .shop-logo {
            font-size: 20px;
            margin-bottom: 2px;
        }

        .main-label {
            font-size: 14px;
        }

        .divider {
            border-top: 2px dashed #000;
            margin: 8px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            font-size: 13px;
            padding: 2px 0;
        }

        th {
            border-bottom: 1px solid #000;
        }

        td {
            color: #000;
        }

        .totals {
            font-size: 14px;
            font-weight: bold;
        }

        .total-label {
            text-align: left;
        }

        .total-value {
            text-align: right;
            width: 70px;
        }

        .policy {
            font-size: 10.5px;
            line-height: 1.5;
            margin-bottom: 6px;
            margin-top: 6px;
        }
    </style>
</head>

<body>
    <div class="receipt">
        <div class="center shop-logo bold">AMZ Traders</div>
        <div class="center main-label">Main Branch</div>
        <div class="center" style="font-size:12px; margin-bottom: 2px;">
            <span class="bold">Ph: 0322-3190100, 0301-7662525</span>
        </div>
        <div class="divider"></div>
        <table>
            <tr>
                <td class="bold">Invoice#</td>
                <td>{{ $sale->id }}</td>
            </tr>
            <tr>
                <td class="bold">Date</td>
                <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y, H:i') }}</td>
            </tr>
            <tr>
                <td class="bold">Sold By</td>
                <td>{{ $sale->user->name ?? '-' }}</td>
            </tr>
            @if($sale->vendor)
            <tr>
                <td class="bold">Vendor</td>
                <td>{{ $sale->vendor->name }}</td>
            </tr>
            <tr>
                <td class="bold">Mobile</td>
                <td>{{ $sale->vendor->mobile_no ?? '-' }}</td>
            </tr>
            @elseif($sale->customer_name)
            <tr>
                <td class="bold">Customer</td>
                <td>{{ $sale->customer_name }}</td>
            </tr>
            <tr>
                <td class="bold">Mobile</td>
                <td>+{{ $sale->customer_mobile ?? '-' }}</td>
            </tr>
            @endif
        </table>
        <div class="divider"></div>
        <table>
            <thead>
                <tr>
                    <th style="text-align:left;">Item</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                <tr>
                    <td style="max-width:38mm;white-space:normal;word-break:break-word;" class="bold">{{
                        $item->batch->accessory->name ?? '-' }}</td>
                    <td style="text-align:right;" class="bold">{{ $item->quantity }}</td>
                    <td style="text-align:right;" class="bold">{{ number_format($item->price_per_unit,0) }}</td>
                    <td style="text-align:right;" class="bold">{{ number_format($item->subtotal,0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="divider"></div>
        <table>
            <tr>
                <td class="totals total-label" colspan="3">TOTAL</td>
                <td class="totals total-value">Rs. {{ number_format($sale->total_amount) }}</td>
            </tr>
        </table>
        <div class="divider"></div>
        <div class="policy">
            <div class="bold" style="font-size:11.5px; text-align:center; margin-bottom:2px;">Return & Exchange Policy:
            </div>
            
        </div>
            <div class="bold" style="font-size:11.5px; text-align:right; margin-bottom:2px;">
                <b> موبائل اسیسری موقع پہ چیک کریں •</b><br>
            <b> وارنٹی والی چیز کی کمپنی ذمہ دار ہوگی •</b><br>
            <b> استعمال شدہ اور کھلی ہوئی چیز کی واپسی نہیں ہوگی •</b><br>
            </div>
            
        </div>
        <div class="divider"></div>
        <div class="center bold" style="font-size:13px;">
            Thank you for shopping!<br>
            <span style="font-size:10px;">Powered by AMZ Traders POS</span>
        </div>
        <div class="no-print center" style="margin-top:10px;">
            <button onclick="window.print()" style="padding:5px 16px;font-size:13px;">Print</button>
        </div>
    </div>
</body>

</html>