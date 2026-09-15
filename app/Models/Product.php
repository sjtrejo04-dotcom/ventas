<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'cost',
        'has_vat',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'has_vat' => 'boolean',
        ];
    }

    public function expenseItems(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function expenses(): BelongsToMany
    {
        return $this->belongsToMany(Expense::class, 'expense_items')
            ->withPivot(['quantity', 'unit_cost', 'margin_percent', 'selling_price', 'has_vat', 'subtotal', 'vat_amount'])
            ->withTimestamps();
    }
}
