<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('users.{publicId}', function ($user, $publicId) {
    return hash_equals($user->public_id, $publicId);
});

Broadcast::channel('conversations.{conversation}', function ($user, $conversation) {
    $conversation = Conversation::query()->where('public_id', $conversation)->first();

    if (! $conversation || ! $user->can('view', $conversation)) {
        return false;
    }

    return [
        'id' => $user->public_id,
        'name' => $user->name,
    ];
});
