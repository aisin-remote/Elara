<?php

namespace App\Models;

use App\Enums\WorkspaceMemberStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Message extends Model
{
    use GeneratesPublicId, HasFactory, SoftDeletes;

    protected $fillable = ['conversation_id', 'sender_id', 'body', 'edited_at'];

    protected $casts = ['edited_at' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(ProjectFile::class, 'message_attachments', 'message_id', 'file_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (Auth::check()) {
            $query
                ->whereHas('conversation.participantRecords', fn (Builder $participant) => $participant->where('user_id', Auth::id()))
                ->whereHas('conversation.workspace.memberships', fn (Builder $membership) => $membership
                    ->where('user_id', Auth::id())
                    ->where('status', WorkspaceMemberStatus::ACTIVE->value));
        }

        return $query;
    }
}
