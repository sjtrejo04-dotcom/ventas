<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    protected $fillable = [
        'user_id',
        'provider_id',
        'payment_status',
        'payment_method_id',
        'invoice_number',
        'control_number',
        'description',
        'total_base',
        'total_exempt',
        'total_vat',
        'total_amount',
        'expense_date',
        'invoice_date',
        'accounting_date',
        'due_date',
    ];

    protected $casts = [
        'total_base' => 'decimal:2',
        'total_exempt' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'expense_date' => 'date',
        'invoice_date' => 'date',
        'due_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'expense_items')
            ->withPivot(['quantity', 'unit_cost', 'margin_percent', 'selling_price', 'has_vat', 'subtotal', 'vat_amount'])
            ->withTimestamps();
    }
}
