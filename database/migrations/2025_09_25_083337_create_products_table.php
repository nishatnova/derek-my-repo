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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('code')->unique();
            $table->longText('description');
            $table->integer('minimum_quantity');
            $table->decimal('per_price', 10, 2);
            $table->json('colors')->nullable();
            $table->json('sizes')->nullable();
            $table->json('additional_discounts')->nullable();
            $table->json('photos')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('category');
            $table->index('code');
            $table->index('is_active');
            $table->index(['category', 'is_active']);
            $table->index(['name', 'category', 'id']);
            $table->fullText(['name', 'category', 'code', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
