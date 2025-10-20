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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

   // User and Product relationship
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            // Payment type
            $table->enum('payment_type', ['half', 'full']);
            
            // Product Information
            $table->json('product_info'); // [{gender, size, pieces}, ...]
            $table->integer('total_pieces')->nullable();
            $table->json('colors')->nullable(); // ['red', 'blue', ...]
            
            
            // Pricing
            $table->decimal('price_per_piece', 10, 2);
            $table->decimal('product_total', 10, 2);
            $table->decimal('delivery_charge', 10, 2)->default(20.00);
            $table->decimal('grand_total', 10, 2);
            $table->decimal('payment_amount', 10, 2)->nullable();
            
            // Delivery Information
            $table->string('organization_name');
            $table->string('email');
            $table->string('phone');
            $table->string('country');
            $table->string('city');
            $table->string('state');
            $table->string('zip_code');
            $table->text('address');
            
            // Additional Information
            $table->text('additional_notes')->nullable();
            $table->json('logo_catalogue')->nullable(); // Multiple files
            $table->string('product_document')->nullable(); // Single file
            
            // Status
            $table->enum('order_status', ['pending', 'in-progress', 'completed', 'cancelled'])->default('pending');
            $table->string('payment_status')->default('pending');

            $table->string('payment_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('product_id');
            $table->index('payment_type');
            $table->index('order_status');
            $table->index('payment_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
