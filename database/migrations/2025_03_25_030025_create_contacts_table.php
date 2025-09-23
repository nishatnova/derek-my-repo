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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email');       
            $table->string('subject');
            $table->string('business_name')->nullable();
            $table->string('business_category')->nullable();
            $table->text('address')->nullable();
            $table->longText('message');
            $table->timestamps();


            $table->index('name');
            $table->index('email');
            $table->index('phone');
            $table->index('business_category');
            $table->index('created_at');
            $table->index(['email', 'created_at']);
            $table->index(['business_category', 'created_at', 'id']);
            $table->index(['created_at', 'id']);
            $table->index(['name', 'created_at']);
            $table->index(['business_category', 'name']);
            $table->index(['id', 'created_at']);
            $table->fullText(['name', 'business_name', 'subject', 'message']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
