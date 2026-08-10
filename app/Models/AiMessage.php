<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = [
        'ai_conversation_id', 'role', 'body', 'model', 'input_tokens', 'output_tokens', 'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    protected $touches = ['conversation'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
