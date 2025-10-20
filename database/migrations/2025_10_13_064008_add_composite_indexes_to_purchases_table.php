<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->index(['payment_status', 'order_status'], 'idx_payment_order_status');
            
            // Composite index for user purchases query
            $table->index(['user_id', 'payment_status', 'created_at'], 'idx_user_paid_purchases');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('idx_payment_order_status');
            $table->dropIndex('idx_user_paid_purchases');
        });
    }
};
