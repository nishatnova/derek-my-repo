<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'payment_type',
        'product_info',
        'total_pieces',
        'colors',
        'price_per_piece',
        'product_total',
        'delivery_charge',
        'grand_total',
        'payment_amount',
        'organization_name',
        'email',
        'phone',
        'country',
        'city',
        'state',
        'zip_code',
        'address',
        'additional_notes',
        'logo_catalogue',
        'product_document',
        'order_status',
        'payment_status',
        'payment_id',
    ];

    protected $casts = [
        'product_info' => 'array',
        'colors' => 'array',
        'logo_catalogue' => 'array',
        'price_per_piece' => 'decimal:2',
        'product_total' => 'decimal:2',
        'delivery_charge' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'payment_amount' => 'decimal:2',
    ];

    /**
     * Get the user that owns the purchase
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product for this purchase
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope for filtering by order status
     */
    public function scopeOrderStatus($query, string $orderStatus)
    {
        return $query->where('order_status', $orderStatus);
    }
    /**
     * Scope for filtering by payment status
     */
    public function scopePaymentStatus($query, string $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    /**
     * Scope for pending purchases
     */
    public function scopeOrderPending($query)
    {
        return $query->where('order_status', 'pending');
    }
}
