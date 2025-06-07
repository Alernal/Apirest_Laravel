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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('address_id')->nullable()->constrained()->onDelete('set null');

            $table->string('payment_method')->default('wompi');
            $table->string('payment_status')->default('pending');
            $table->string('status')->default('pending'); // pending, processing, completed, etc.
            $table->string('shipping_method')->nullable();
            $table->decimal('shipping_cost', 10, 2)->default(0.00);
            $table->decimal('tax', 10, 2)->default(0.00);

            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);

            $table->string('transaction_id')->nullable();
            $table->timestamps();
        });

        Schema::create('order_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            // Snapshot del producto al momento de la orden
            $table->string('product_name');
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->decimal('total', 10, 2); // price * quantity

            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null'); // quién hizo el cambio

            $table->string('status'); // Ej: pending, processing, shipped, completed
            $table->text('message')->nullable(); // mensaje opcional del admin
            $table->string('tracking_url')->nullable(); // solo si aplica (envío)

            $table->timestamps(); // created_at = cuándo se hizo el cambio
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_product');
        Schema::dropIfExists('orders');
    }
};
