<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $invoice_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }
        .header .logo img {
            max-width: 120px;
            max-height: 60px;
        }
        .header h1 {
            color: #667eea;
            margin: 8px 0;
            font-size: 28px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 10px;
            background-color: #48bb78;
            color: white;
        }
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            background-color: #667eea;
            color: white;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 3px;
            margin-bottom: 10px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-grid td {
            padding: 6px 10px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        .info-label {
            font-weight: bold;
            width: 30%;
            background-color: #f9f9f9;
        }
        .billing-box {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            padding: 12px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-right: 2%;
            box-sizing: border-box;
        }
        .billing-box:last-child {
            margin-right: 0;
        }
        .billing-box h3 {
            color: #667eea;
            font-size: 12px;
            margin: 0 0 8px 0;
            border-bottom: 2px solid #667eea;
            padding-bottom: 4px;
        }
        .billing-box p {
            margin: 3px 0;
            font-size: 10px;
        }
        .product-details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .product-details-table th {
            background-color: #667eea;
            color: white;
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        .product-details-table td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        .product-details-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .color-display {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .color-circle {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1px solid #ddd;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            vertical-align: middle;
        }
        
        .color-code {
            font-size: 12px;
            color: #666;
            font-family: monospace;
        }
        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .pricing-table th {
            background-color: #f5f5f5;
            padding: 6px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        .pricing-table td {
            padding: 6px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        .totals-table {
            width: 350px;
            margin-left: auto;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .totals-table td {
            padding: 8px 15px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        .total-label {
            font-weight: bold;
            text-align: right;
            width: 60%;
        }
        .total-value {
            text-align: right;
            width: 40%;
        }
        .grand-total {
            background-color: #667eea;
            color: white;
            font-size: 14px;
            font-weight: bold;
        }
        .discount-badge {
            background-color: #48bb78;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .notes-box {
            background-color: #fff9e6;
            padding: 10px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
            border-radius: 3px;
        }
        .notes-box h4 {
            margin: 0 0 5px 0;
            color: #f59e0b;
            font-size: 11px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        @if(isset($logo) && $logo)
        <div class="logo">
            <img src="{{ $logo }}" alt="Company Logo">
        </div>
        @endif
        <h1>INVOICE</h1>
        <p style="font-size: 14px; font-weight: bold; margin: 5px 0;">{{ $invoice_number }}</p>
        <p><span class="status-badge">PAID</span></p>
        <p style="font-size: 9px; margin: 5px 0;">Generated: {{ $generated_at }}</p>
    </div>

    <!-- Invoice & Transaction Info -->
    <div class="section">
        <div class="section-title">📋 Invoice Information</div>
        <table class="info-grid">
            <tr>
                <td class="info-label">Transaction ID</td>
                <td>{{ $purchase->payment_id }}</td>
                <td class="info-label">Invoice Date</td>
                <td>{{ $purchase->updated_at->format('F d, Y') }}</td>
            </tr>
            <tr>
                <td class="info-label">Payment Type</td>
                <td>{{ $purchase->payment_type === 'half' ? 'Partial Payment (50%)' : 'Full Payment' }}</td>
                <td class="info-label">Payment Status</td>
                <td><span class="status-badge">{{ strtoupper($purchase->payment_status) }}</span></td>
            </tr>
        </table>
    </div>

    <!-- Customer & Delivery Info -->
    <div class="section">
        <div class="section-title">👤 Customer & Delivery Information</div>
        <div>
            <div class="billing-box">
                <h3>Customer Details</h3>
                <p><strong>{{ $purchase->organization_name }}</strong></p>
                <p>Email: {{ $purchase->email }}</p>
                <p>Phone: {{ $purchase->phone }}</p>
            </div>
            
            <div class="billing-box">
                <h3>Delivery Address</h3>
                <p>{{ $purchase->address }}</p>
                <p>{{ $purchase->city }}, {{ $purchase->state }} {{ $purchase->zip_code }}</p>
                <p>{{ $purchase->country }}</p>
            </div>
        </div>
    </div>

    <!-- Product Details -->
    <div class="section">
        <div class="section-title">🛍️ Product Information</div>
        <table class="info-grid">
            <tr>
                <td class="info-label">Product Code</td>
                <td><strong>{{ $product->code }}</strong></td>
                <td class="info-label">Product Name</td>
                <td><strong>{{ $product->name }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">Category</td>
                <td>{{ ucfirst(str_replace('_', ' ', $product->category)) }}</td>
                <td class="info-label">Fabric</td>
                <td>{{ $product->fabric ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <!-- Order Breakdown -->
    <div class="section">
        <div class="section-title">📦 Order Breakdown</div>
        <table class="product-details-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Audience</th>
                    <th style="width: 25%;">Size</th>
                    <th style="width: 20%; text-align: center;" class="text-center">Color</th>
                    <th style="width: 20%; text-align: center;" class="text-center">Pieces</th>      
                    <th style="width: 30%; text-align: center;" class="text-center">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $itemTotal = 0;
                @endphp
                @foreach($purchase->product_info as $item)
                @php
                    $itemSubtotal = $item['pieces'] * $purchase->product->per_price;
                    $itemTotal += $itemSubtotal;

                    $color = $item['color'] ?? '#cccccc';
                    
                    if (!empty($color) && $color !== 'N/A' && !str_starts_with($color, '#')) {
                        if (preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
                            $color = '#' . $color;
                        }
                    }
                @endphp
                <tr>
                    <td>{{ $item['gender'] }}</td>
                    <td>{{ $item['size'] }}</td>
                    <td class="text-center">
                        @if(!empty($item['color']) && $item['color'] !== 'N/A')
                            <div class="color-display">
                                <span class="color-circle" style="background-color: {{ $color }};"></span>
                                <span class="color-code">{{ strtoupper($color) }}</span>
                            </div>
                        @else
                            <span style="color: #999;">N/A</span>
                        @endif
                    </td>
                    <td class="text-center"><strong>{{ number_format($item['pieces']) }}</strong></td>
                    <td class="text-right">${{ number_format($itemSubtotal, 2) }}</td>
                </tr>
                @endforeach
                <tr style="background-color: #f0f0f0; font-weight: bold;">
                    <td colspan="3" class="text-right">Total Pieces:</td>
                    <td class="text-center">{{ number_format($purchase->total_pieces) }}</td>
                    <td class="text-right">${{ number_format($itemTotal, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pricing Details -->
    <div class="section">
        <div class="section-title">💰 Pricing Details</div>
        <table class="pricing-table">
            <tr>
                <th style="width: 40%;">Description</th>
                <th style="width: 20%; text-align: center;" class="text-center">Quantity</th>
                <th style="width: 20%; text-align: center;" class="text-right">Unit Price</th>
                <th style="width: 20%; text-align: center;" class="text-right">Amount</th>
            </tr>
            <tr>
                <td>
                    @if($purchase->price_per_piece < $product->per_price)
                    <br><span class="discount-badge">BULK DISCOUNT APPLIED</span>
                    @else
                    Standard Pricing (No Discount)
                    @endif
                </td>
                <td class="text-center">{{ number_format($purchase->total_pieces) }} pcs</td>
                <td class="text-right">
                    @if($purchase->price_per_piece < $product->per_price)
                    <s style="color: #999;">${{ number_format($product->per_price, 2) }}</s><br>
                    @endif
                    <strong>${{ number_format($purchase->price_per_piece, 2) }}</strong>
                </td>
                <td class="text-right"><strong>${{ number_format($purchase->product_total, 2) }}</strong></td>
            </tr>
        </table>

        @if(isset($product->additional_discounts) && !empty($product->additional_discounts) && ($purchase->price_per_piece < $product->per_price))
        <div style="background-color: #f9f9f9; padding: 8px; border-radius: 3px; margin-top: 10px;">
            <strong style="font-size: 10px; color: #667eea;">Volume Discount Tiers:</strong>
            <table style="width: 100%; margin-top: 5px; font-size: 9px;">
                @foreach($product->additional_discounts as $discount)
                <tr>
                    <td style="padding: 2px;">{{ $discount['min_quantity'] }}-{{ $discount['max_quantity'] }} pieces</td>
                    <td style="text-align: right; padding: 2px;">${{ number_format($discount['price'], 2) }}/pc</td>
                </tr>
                @endforeach
            </table>
        </div>
        @endif
    </div>

    <!-- Cost Summary -->
    <div class="section">
        <div class="section-title">📊 Cost Summary</div>
        <table class="totals-table">
            <tr>
                <td class="total-label">Product Subtotal:</td>
                <td class="total-value">${{ number_format($purchase->product_total, 2) }}</td>
            </tr>
            <tr>
                <td class="total-label">Delivery Charge:</td>
                <td class="total-value">${{ number_format($purchase->delivery_charge, 2) }}</td>
            </tr>
            <tr style="border-top: 2px solid #333;">
                <td class="total-label">Grand Total:</td>
                <td class="total-value" style="font-size: 13px;"><strong>${{ number_format($purchase->grand_total, 2) }}</strong></td>
            </tr>
            @if($purchase->payment_type === 'half')
            <tr style="background-color: #fff9e6;">
                <td class="total-label">Payment Type:</td>
                <td class="total-value">50% Partial Payment</td>
            </tr>
            @endif
            <tr class="grand-total">
                <td class="total-label">AMOUNT PAID:</td>
                <td class="total-value">${{ number_format($purchase->payment_amount, 2) }}</td>
            </tr>
            @if($purchase->payment_type === 'half')
            <tr style="background-color: #ffe6e6;">
                <td class="total-label">Remaining Balance:</td>
                <td class="total-value" style="color: #dc2626; font-weight: bold;">${{ number_format($purchase->grand_total - $purchase->payment_amount, 2) }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Payment Confirmation -->
    <div style="background-color: #e6f3ff; padding: 12px; border-radius: 3px; border-left: 4px solid #667eea; margin-top: 15px;">
        <h3 style="color: #667eea; font-size: 12px; margin: 0 0 8px 0;">✓ Payment Confirmed</h3>
        <p style="font-size: 10px; margin: 3px 0;"><strong>Thank you for your purchase!</strong></p>
        <p style="font-size: 10px; margin: 3px 0;">Payment has been successfully received and processed.</p>
        @if($purchase->payment_type === 'half')
        <p style="font-size: 10px; margin: 3px 0; color: #dc2626;"><strong>Note:</strong> This is a partial payment. Remaining balance of ${{ number_format($purchase->grand_total - $purchase->payment_amount, 2) }} is due.</p>
        @endif
    </div>

    <!-- Footer -->
    <div class="footer">
        <p><strong>Invoice:</strong> {{ $invoice_number }} | <strong>Transaction:</strong> {{ $purchase->payment_id }}</p>
        <p>For questions, contact: {{ config('app.support_mail', 'X-treme Sports') }}</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'X-treme Sports') }}. All rights reserved.</p>
        <p style="font-size: 8px; color: #999; margin-top: 5px;">This is a computer-generated invoice. No signature required.</p>
    </div>
</body>
</html>