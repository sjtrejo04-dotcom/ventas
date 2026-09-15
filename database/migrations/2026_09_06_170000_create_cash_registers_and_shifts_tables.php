<?php

declare(strict_types=1);

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
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 20)->default('open');

            // Apertura
            $table->decimal('opening_cash_bs', 15, 2)->default(0);
            $table->decimal('opening_cash_usd', 15, 2)->default(0);

            // Cierre del Sistema (calculado automáticamente)
            $table->decimal('system_cash_bs', 15, 2)->default(0);
            $table->decimal('system_cash_usd', 15, 2)->default(0);
            $table->decimal('system_pos_bs', 15, 2)->default(0);
            $table->decimal('system_mobile_pay_bs', 15, 2)->default(0);
            $table->decimal('system_cashea_bs', 15, 2)->default(0);

            // Arqueo Ciego (declarado por el cajero)
            $table->decimal('declared_cash_bs', 15, 2)->nullable();
            $table->decimal('declared_cash_usd', 15, 2)->nullable();
            $table->decimal('declared_pos_bs', 15, 2)->nullable();
            $table->decimal('declared_mobile_pay_bs', 15, 2)->nullable();

            // Diferencias registradas
            $table->decimal('difference_cash_bs', 15, 2)->nullable();
            $table->decimal('difference_cash_usd', 15, 2)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
            $table->string('pos_document_number')->nullable();
            $table->string('fiscal_serial')->nullable();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 3)->change();
            $table->string('serial_number')->nullable();
            $table->integer('warranty_days')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['serial_number', 'warranty_days']);
            $table->decimal('quantity', 10, 2)->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['cash_shift_id']);
            $table->dropColumn(['cash_shift_id', 'pos_document_number', 'fiscal_serial']);
        });

        Schema::dropIfExists('cash_shifts');
        Schema::dropIfExists('cash_registers');
    }
};
