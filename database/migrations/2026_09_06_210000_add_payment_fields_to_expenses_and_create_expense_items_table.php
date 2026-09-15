<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('paid')->after('provider_id');
            $table->foreignId('payment_method_id')->nullable()->after('payment_status')->constrained('payment_methods')->nullOnDelete();
            $table->decimal('total_exempt', 15, 2)->default(0.00)->after('total_base');
        });

        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 2)->default(1.00);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('margin_percent', 5, 2)->default(30.00);
            $table->decimal('selling_price', 15, 2);
            $table->boolean('has_vat')->default(true);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('vat_amount', 15, 2)->default(0.00);
            $table->timestamps();

            $table->index('expense_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn(['payment_status', 'payment_method_id', 'total_exempt']);
        });
    }
};
