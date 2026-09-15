<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZReport extends Model
{
    protected $fillable = [
        'user_id',
        'z_number',
        'start_invoice_number',
        'end_invoice_number',
        'total_exempt',
        'total_base',
        'total_vat',
        'total_igtf',
        'report_date',
    ];

    protected $casts = [
        'total_exempt' => 'decimal:2',
        'total_base' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_igtf' => 'decimal:2',
        'report_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
