<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('OrderData', function (Blueprint $table) {
            $table->string('OrderId', 40);
            $table->primary('OrderId');
            $table->string('Email', 200)->nullable();
            $table->string('Phone', 50)->nullable();
            $table->string('Name', 200)->nullable();
            $table->string('ProductName', 300)->nullable();
            $table->decimal('VariantPrice', 14, 2)->nullable();
            $table->decimal('NetRevenue', 14, 2)->nullable();
            $table->string('Status', 30)->default('Not Paid');
            $table->dateTime('CreatedAt')->useCurrent();
            $table->dateTime('UpdatedAt')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('OrderData');
    }
};