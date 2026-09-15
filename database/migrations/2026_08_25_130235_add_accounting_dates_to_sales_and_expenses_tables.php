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
        Schema::table('sales', function (Blueprint $table) {
            $table->date('invoice_date')->nullable()->after('id');
            $table->date('accounting_date')->nullable()->after('invoice_date');
            $table->date('due_date')->nullable()->after('accounting_date');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->date('invoice_date')->nullable()->after('id');
            $table->date('accounting_date')->nullable()->after('invoice_date');
            $table->date('due_date')->nullable()->after('accounting_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['invoice_date', 'accounting_date', 'due_date']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['invoice_date', 'accounting_date', 'due_date']);
        });
    }
};
