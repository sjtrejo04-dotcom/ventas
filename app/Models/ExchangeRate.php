<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency',
        'rate',
        'date_published',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'date_published' => 'datetime',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
