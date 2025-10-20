<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Product Details - {{ $product->name }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4CAF50;
        }
        .header .logo {
            margin-bottom: 15px;
        }
        .header .logo img {
            max-width: 150px;
            max-height: 80px;
        }
        .header h1 {
            color: #4CAF50;
            margin: 10px 0;
            font-size: 28px;
        }
        .header p {
            color: #666;
            margin: 5px 0;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            background-color: #f5f5f5;
            padding: 10px;
            font-size: 16px;
            font-weight: bold;
            color: #4CAF50;
            border-left: 4px solid #4CAF50;
            margin-bottom: 15px;
        }
        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .info-row {
            display: table-row;
            margin: 0;
            padding: 0;
        }
        .info-label {
            display: table-cell;
            padding: 8px 10px;
            font-weight: bold;
            width: 35%;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .info-value {
            display: table-cell;
            padding: 8px 10px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
        }
        .status-active {
            background-color: #4CAF50;
            color: white;
        }
        .status-inactive {
            background-color: #f44336;
            color: white;
        }
        .discount-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            margin-bottom: 0;
        }
        .discount-table th {
            background-color: #4CAF50;
            color: white;
            padding: 12px 10px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }
        .discount-table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .discount-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .description-box {
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            line-height: 1.8;
        }
        .images-section {
            margin-top: 0;
        }
        .image-grid {
            width: 100%;
            margin-bottom: 0;
        }
        .image-row {
            width: 100%;
            margin-bottom: 5px;
            overflow: hidden;
        }
        .image-item {
            display: inline-block;
            width: 48%;
            padding: 5px;
            text-align: center;
            vertical-align: top;
            margin: 0;
            box-sizing: border-box;
        }
        .image-item img {
            max-width: 100%;
            max-height: 180px;
            border: 2px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            background-color: white;
            display: block;
            margin: 0 auto;
        }
        .image-caption {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
            margin-bottom: 0;
        }
        .price-highlight {
            font-size: 18px;
            font-weight: bold;
            color: #4CAF50;
        }
        .no-data {
            color: #999;
            font-style: italic;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        .footer p {
            margin: 5px 0;
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
        <h1>PRODUCT DETAILS</h1>
        <p style="font-size: 14px; font-weight: bold;">{{ $product->name }}</p>
        <p style="font-size: 10px;">Generated on: {{ $generated_at }}</p>
    </div>

    <!-- Basic Information -->
    <div class="section">
        <div class="section-title">📋 Basic Information</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Product Name</div>
                <div class="info-value">{{ $product->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Product Code</div>
                <div class="info-value"><strong>{{ $product->code }}</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Category</div>
                <div class="info-value">{{ ucfirst(str_replace('_', ' ', $product->category)) }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fabric Material</div>
                <div class="info-value">{{ $product->fabric ?? 'Not specified' }}</div>
            </div>
        </div>
    </div>

    <!-- Description -->
    <div class="section">
        <div class="section-title">📝 Product Description</div>
        <div class="description-box">
            @if(!empty($product->description))
            {!! nl2br(e(strip_tags($product->description))) !!}
            @else
                <em>No description available.</em>
            @endif
        </div>
    </div>

    <!-- Pricing Information -->
    <div class="section">
        <div class="section-title">💰 Pricing Information</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Base Price (Per Unit)</div>
                <div class="info-value">
                    <span class="price-highlight">${{ number_format($product->per_price, 2) }}</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Minimum Order Quantity</div>
                <div class="info-value">
                    <strong>{{ $product->minimum_quantity }}</strong> units
                </div>
            </div>
            
        </div>
    </div>

    <!-- Additional Discounts -->
    @if($product->additional_discounts && count($product->additional_discounts) > 0)
    <div class="section">
        <div class="section-title">🎯 Quantity-Based Discount Tiers</div>
        <table class="discount-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Tier</th>
                    <th style="width: 25%;">Min Quantity</th>
                    <th style="width: 25%;">Max Quantity</th>
                    <th style="width: 30%;">Discounted Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach($product->additional_discounts as $index => $discount)
                <tr>
                    <td><strong>Tier {{ $index + 1 }}</strong></td>
                    <td>{{ number_format($discount['min_quantity']) }} units</td>
                    <td>{{ number_format($discount['max_quantity']) }} units</td>
                    <td style="color: #4CAF50; font-weight: bold;">
                        ${{ number_format($discount['price'], 2) }} per unit
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="section">
        <div class="section-title">🎯 Quantity-Based Discount Tiers</div>
        <div class="description-box">
            <span class="no-data">No quantity-based discounts available for this product.</span>
        </div>
    </div>
    @endif

    <!-- Product Images -->
    @if($images_base64 && count($images_base64) > 0)
    <div class="section">
        <div class="section-title">📸 Product Images ({{ count($images_base64) }} images)</div>
        <div class="images-section">
            @foreach($images_base64 as $index => $imageBase64)
                @if($index % 2 == 0)
                    <div class="image-row">
                @endif
                        <div class="image-item">
                            <img src="{{ $imageBase64 }}" alt="Product Image">
                            <div class="image-caption">Image {{ $index + 1 }}</div>
                        </div>
                @if($index % 2 == 1 || $index == count($images_base64) - 1)
                    </div>
                @endif
            @endforeach
        </div>
    </div>
    @else
    <div class="section">
        <div class="section-title">📸 Product Images</div>
        <div class="description-box">
            <span class="no-data">No images available for this product.</span>
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p><strong>Product Code:</strong> {{ $product->code }} | <strong>Category:</strong> {{ ucfirst(str_replace('_', ' ', $product->category)) }}</p>
        <p>This is a computer-generated document. No signature is required.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'Your Company') }}. All rights reserved.</p>
        <p style="font-size: 9px; color: #999;">Document generated at {{ $generated_at }}</p>
    </div>
</body>
</html>