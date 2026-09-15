<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'invoice_number',
        'total_base',
        'total_vat',
        'total_igtf',
        'total_amount',
        'status',
        'invoice_date',
        'accounting_date',
        'due_date',
        'cash_shift_id',
        'pos_document_number',
        'fiscal_serial',
    ];

    protected $casts = [
        'total_base' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_igtf' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
