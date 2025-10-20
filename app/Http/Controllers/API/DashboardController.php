<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ResponseTrait;

    /**
     * Get dashboard statistics (ultra-optimized)
     */
    public function getDashboardStats()
    {
        $purchaseStats = DB::table('purchases')
            ->selectRaw("
                COUNT(CASE WHEN payment_status = 'paid' AND order_status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN payment_status = 'paid' AND order_status = 'pending' THEN 1 END) as pending_orders,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN payment_amount END), 0) as total_revenue
            ")
            ->first();

        // Get product and user counts
        $totalProducts = DB::table('products')->count();
        $totalUsers = DB::table('users')->count();

        // Build response
        $response = [
            'total_products' => $totalProducts,
            'total_users' => $totalUsers,
            'completed_orders' => $purchaseStats->completed_orders,
            'pending_orders' => $purchaseStats->pending_orders,
            'total_revenue' => number_format($purchaseStats->total_revenue, 2, '.', ''),
        ];

        return $this->sendResponse($response, 'Dashboard statistics retrieved successfully');
    }
}
