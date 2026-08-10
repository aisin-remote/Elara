<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\OrbitraNotification;

class NotificationPreferenceService
{
    public const EVENTS = [
        'task_assigned' => 'Task assigned to me',
        'task_status_changed' => 'Task status changed',
        'comment_mention' => 'Comments and mentions',
        'deadline_reminder' => 'Deadline reminders',
        'project_updated' => 'Project updates',
        'team_activity' => 'Team activity',
        'message_received' => 'New messages',
        'feature_request' => 'Feature requests and approvals',
        'project_request' => 'Project requests and approvals',
        'task_breakdown' => 'Proposed plans waiting for my acceptance',
        'validation_checkpoint' => 'Things waiting for my confirmation',
    ];

    public const CHANNELS = ['mail', 'database', 'broadcast', 'push'];

    public function values(User $user, Workspace $workspace): array
    {
        $stored = $user->notificationPreferences()->where('workspace_id', $workspace->id)->get()
            ->keyBy(fn (NotificationPreference $preference) => $preference->event.'.'.$preference->channel);

        return collect(self::EVENTS)->mapWithKeys(function (string $label, string $event) use ($stored) {
            $enabled = fn (string $channel, bool $default) => (bool) ($stored->get($event.'.'.$channel)?->enabled ?? $default);

            return [$event => [
                'label' => $label,
                'mail' => $enabled('mail', false),
                'in_app' => $enabled('database', true) || $enabled('broadcast', true),
                'push' => $enabled('push', false),
            ]];
        })->all();
    }

    public function update(User $user, Workspace $workspace, array $preferences): array
    {
        foreach (self::EVENTS as $event => $label) {
            if (! isset($preferences[$event])) {
                continue;
            }

            $channels = [
                'mail' => (bool) ($preferences[$event]['mail'] ?? false),
                'database' => (bool) ($preferences[$event]['in_app'] ?? false),
                'broadcast' => (bool) ($preferences[$event]['in_app'] ?? false),
                'push' => (bool) ($preferences[$event]['push'] ?? false),
            ];

            foreach ($channels as $channel => $enabled) {
                NotificationPreference::query()->updateOrCreate([
                    'user_id' => $user->id,
                    'workspace_id' => $workspace->id,
                    'channel' => $channel,
                    'event' => $event,
                ], ['enabled' => $enabled]);
            }
        }

        return $this->values($user, $workspace);
    }

    public function channelsFor(User $user, Workspace $workspace, string $event): array
    {
        $stored = $user->notificationPreferences()
            ->where('workspace_id', $workspace->id)
            ->where('event', $event)
            ->get()
            ->keyBy('channel');

        $enabled = [
            'mail' => false,
            'database' => true,
            'broadcast' => true,
            'push' => false,
        ];

        foreach ($enabled as $channel => $default) {
            $enabled[$channel] = (bool) ($stored->get($channel)?->enabled ?? $default);
        }

        return collect($enabled)->filter()->keys()->all();
    }

    public function notify(User $recipient, Workspace $workspace, string $event, string $title, string $body, string $url, array $meta = []): void
    {
        $channels = $this->channelsFor($recipient, $workspace, $event);

        if ($channels !== []) {
            $recipient->notify(new OrbitraNotification($channels, $event, $workspace->public_id, $title, $body, $url, $meta));
        }
    }
}
