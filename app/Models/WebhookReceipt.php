<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookReceipt extends Model
{
    protected $fillable = ['provider', 'external_id', 'payload_hash', 'processing_at', 'processed_at'];

    protected $casts = ['processing_at' => 'datetime', 'processed_at' => 'datetime'];
}
