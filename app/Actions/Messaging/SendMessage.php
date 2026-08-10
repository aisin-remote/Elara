<?php

namespace App\Actions\Messaging;

use App\Actions\File\StorePrivateFile;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProjectFile;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendMessage
{
    public function __construct(
        private readonly StorePrivateFile $storeFile,
        private readonly NotificationPreferenceService $notifications,
    ) {}

    /** @param array<int, UploadedFile> $attachments */
    public function handle(Conversation $conversation, User $sender, ?string $body, array $attachments = []): Message
    {
        $storedFiles = [];

        try {
            $message = DB::transaction(function () use ($conversation, $sender, $body, $attachments, &$storedFiles) {
                $message = $conversation->messages()->create([
                    'sender_id' => $sender->id,
                    'body' => filled($body) ? trim($body) : null,
                ]);

                foreach ($attachments as $upload) {
                    $file = $this->storeFile->handle($conversation->workspace, $sender, $upload, $conversation->project);
                    $storedFiles[] = $file;
                    $message->attachments()->attach($file->id);
                }

                $conversation->update(['last_message_at' => $message->created_at]);

                return $message->load(['sender', 'attachments', 'reactions.user', 'conversation.participantRecords.user']);
            });
        } catch (Throwable $exception) {
            collect($storedFiles)->each(fn (ProjectFile $file) => Storage::disk($file->disk)->delete($file->path));
            throw $exception;
        }

        event(new MessageSent($message));

        $message->conversation->participantRecords
            ->where('user_id', '!=', $sender->id)
            ->filter(fn ($participant) => ! $participant->muted_until || $participant->muted_until->isPast())
            ->each(fn ($participant) => $this->notifications->notify(
                $participant->user,
                $conversation->workspace,
                'message_received',
                'New message from '.$sender->name,
                $message->body ?: 'Sent an attachment',
                route('app.messages.index', $conversation->workspace).'?conversation='.$conversation->public_id,
                ['conversation_public_id' => $conversation->public_id, 'message_public_id' => $message->public_id],
            ));

        return $message;
    }
}
