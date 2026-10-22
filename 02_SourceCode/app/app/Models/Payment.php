<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['booking_id', 'gateway', 'txn_ref', 'amount_vnd', 'currency', 'status', 'gateway_payload', 'signature', 'paid_at', 'refunded_at'];

    protected $casts = ['gateway_payload' => 'array', 'paid_at' => 'datetime', 'refunded_at' => 'datetime', 'amount_vnd' => 'integer'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
