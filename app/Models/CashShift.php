<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cash_register_id',
        'user_id',
        'opened_at',
        'closed_at',
        'status',
        'opening_cash_bs',
        'opening_cash_usd',
        'system_cash_bs',
        'system_cash_usd',
        'system_pos_bs',
        'system_mobile_pay_bs',
        'system_cashea_bs',
        'declared_cash_bs',
        'declared_cash_usd',
        'declared_pos_bs',
        'declared_mobile_pay_bs',
        'difference_cash_bs',
        'difference_cash_usd',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash_bs' => 'decimal:2',
            'opening_cash_usd' => 'decimal:2',
            'system_cash_bs' => 'decimal:2',
            'system_cash_usd' => 'decimal:2',
            'system_pos_bs' => 'decimal:2',
            'system_mobile_pay_bs' => 'decimal:2',
            'system_cashea_bs' => 'decimal:2',
            'declared_cash_bs' => 'decimal:2',
            'declared_cash_usd' => 'decimal:2',
            'declared_pos_bs' => 'decimal:2',
            'declared_mobile_pay_bs' => 'decimal:2',
            'difference_cash_bs' => 'decimal:2',
            'difference_cash_usd' => 'decimal:2',
        ];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
