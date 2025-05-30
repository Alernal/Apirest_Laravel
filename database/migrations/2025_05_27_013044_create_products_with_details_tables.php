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
            $table->string('slug', 250)->unique();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->decimal('original_price', 10, 2)->nullable();
            $table->string('size', 5)->nullable();
            $table->string('color', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->integer('stock_count')->default(0);
            $table->timestamps();
        });

        // Tabla: product_images
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('product_colors');
        Schema::dropIfExists('product_features');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
