<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    protected $fillable = [
        'name',
        'rif',
        'address',
        'phone',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
