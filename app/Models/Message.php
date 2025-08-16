<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'alert_id',
        'to_msisdn',
        'template_code',
        'language',
        'status',
        'provider_msg_id',
        'attempts',
        'last_error',
    ];

    protected $casts = [
        'attempts' => 'integer',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }
}
