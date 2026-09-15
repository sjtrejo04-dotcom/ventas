<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'document_type',
        'document_number',
        'name',
        'address',
        'phone',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
