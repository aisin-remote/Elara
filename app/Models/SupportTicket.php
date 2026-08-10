<?php

namespace App\Models;

use App\Enums\SupportTicketStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = ['workspace_id', 'requester_id', 'subject', 'body', 'status', 'resolved_at'];

    protected $casts = ['status' => SupportTicketStatus::class, 'resolved_at' => 'datetime'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
