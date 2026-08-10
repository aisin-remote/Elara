<?php

namespace App\Services;

use App\Enums\IntegrationProvider;
use App\Models\IntegrationConnection;
use App\Models\IntegrationLink;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class IntegrationService
{
    public function redirect(IntegrationProvider $provider, Workspace $workspace): RedirectResponse
    {
        $state = $this->signedState();
        session()->put('integration.oauth', [
            'state' => $state,
            'provider' => $provider->value,
            'workspace_id' => $workspace->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        if ($provider === IntegrationProvider::ZOOM) {
            $config = config('services.zoom');

            return redirect()->away($config['authorize_url'].'?'.http_build_query([
                'response_type' => 'code',
                'client_id' => $config['client_id'],
                'redirect_uri' => url($config['redirect']),
                'state' => $state,
            ]));
        }

        $driver = $this->socialite($provider)->stateless()->setScopes($this->config($provider)['scopes'])->with(['state' => $state]);
        if ($provider === IntegrationProvider::SLACK) {
            $driver->asBotUser();
        }

        return $driver->redirect();
    }

    public function callback(IntegrationProvider $provider, string $code): array
    {
        $oauth = session()->pull('integration.oauth');
        $state = (string) request('state');
        if (! $oauth || $oauth['provider'] !== $provider->value || $oauth['expires_at'] < now()->timestamp || ! hash_equals($oauth['state'], $state) || ! $this->validState($state)) {
            throw ValidationException::withMessages(['state' => 'The OAuth state is invalid or expired.']);
        }

        if ($provider === IntegrationProvider::ZOOM) {
            $config = $this->config($provider);
            $token = Http::asForm()->withBasicAuth($config['client_id'], $config['client_secret'])
                ->post($config['token_url'], ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => url($config['redirect'])])
                ->throw()->json();
            $profile = Http::withToken($token['access_token'])->get($config['api_url'].'/users/me')->throw()->json();

            return [
                'workspace_id' => $oauth['workspace_id'],
                'external_account_id' => (string) $profile['id'],
                'account_name' => $profile['display_name'] ?? $profile['email'] ?? 'Zoom account',
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_in' => $token['expires_in'] ?? null,
                'scopes' => $config['scopes'],
            ];
        }

        $driver = $this->socialite($provider)->stateless();
        if ($provider === IntegrationProvider::SLACK) {
            $driver->asBotUser();
        }
        $remote = $driver->user();

        if ($provider === IntegrationProvider::SLACK) {
            $identity = Http::withToken($remote->token)->post('https://slack.com/api/auth.test')->throw()->json();
            if (! ($identity['ok'] ?? false)) {
                throw ValidationException::withMessages(['provider' => $identity['error'] ?? 'Slack authorization failed.']);
            }

            return [
                'workspace_id' => $oauth['workspace_id'],
                'external_account_id' => (string) ($identity['team_id'] ?? $identity['user_id']),
                'account_name' => $identity['team'] ?? 'Slack workspace',
                'access_token' => $remote->token,
                'refresh_token' => $remote->refreshToken ?? null,
                'expires_in' => $remote->expiresIn ?? null,
                'scopes' => $this->config($provider)['scopes'],
            ];
        }

        return [
            'workspace_id' => $oauth['workspace_id'],
            'external_account_id' => (string) $remote->getId(),
            'account_name' => $remote->getName() ?: $remote->getNickname() ?: $remote->getEmail(),
            'access_token' => $remote->token,
            'refresh_token' => $remote->refreshToken ?? null,
            'expires_in' => $remote->expiresIn ?? null,
            'scopes' => $this->config($provider)['scopes'],
        ];
    }

    public function revoke(IntegrationConnection $connection): void
    {
        if (! $connection->access_token) {
            return;
        }

        $token = $connection->access_token;
        match ($connection->provider) {
            IntegrationProvider::SLACK => Http::withToken($token)->post('https://slack.com/api/auth.revoke')->throw(),
            IntegrationProvider::GOOGLE_DRIVE => Http::asForm()->post('https://oauth2.googleapis.com/revoke', ['token' => $token])->throw(),
            IntegrationProvider::GITHUB => Http::withBasicAuth(config('services.github.client_id'), config('services.github.client_secret'))
                ->delete('https://api.github.com/applications/'.config('services.github.client_id').'/grant', ['access_token' => $token])->throw(),
            IntegrationProvider::ZOOM => Http::asForm()->withBasicAuth(config('services.zoom.client_id'), config('services.zoom.client_secret'))
                ->post('https://zoom.us/oauth/revoke', ['token' => $token])->throw(),
        };
    }

    public function sendSlack(IntegrationConnection $connection, string $channel, string $message): void
    {
        $result = $this->request($connection)->post('https://slack.com/api/chat.postMessage', ['channel' => $channel, 'text' => $message])->throw()->json();
        if (! ($result['ok'] ?? false)) {
            throw ValidationException::withMessages(['channel' => $result['error'] ?? 'Slack rejected the message.']);
        }
        $connection->update(['settings_json' => ['channel' => $channel], 'last_synced_at' => now(), 'status' => 'connected', 'error_message' => null]);
    }

    public function linkDriveFile(IntegrationConnection $connection, Workspace $workspace, Project $project, string $fileId): IntegrationLink
    {
        $file = $this->request($connection)->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId), [
            'fields' => 'id,name,mimeType,webViewLink,modifiedTime',
        ])->throw()->json();

        return $this->storeLink($connection, $workspace, [
            'project_id' => $project->id,
            'resource_type' => 'drive_file',
            'external_id' => $file['id'],
            'name' => $file['name'],
            'url' => $file['webViewLink'] ?? 'https://drive.google.com/open?id='.$file['id'],
            'metadata_json' => ['mime_type' => $file['mimeType'] ?? null, 'modified_time' => $file['modifiedTime'] ?? null],
        ]);
    }

    public function linkGithubResource(IntegrationConnection $connection, Workspace $workspace, Task $task, string $repository, string $url): IntegrationLink
    {
        if (! preg_match('#^https://github\.com/'.preg_quote($repository, '#').'/(commit|pull)/[^/]+/?$#i', $url)) {
            throw ValidationException::withMessages(['url' => 'Enter a commit or pull request URL from the selected repository.']);
        }
        $repo = $this->request($connection)->get('https://api.github.com/repos/'.$repository)->throw()->json();

        return $this->storeLink($connection, $workspace, [
            'task_id' => $task->id,
            'project_id' => $task->project_id,
            'resource_type' => str_contains($url, '/pull/') ? 'github_pull_request' : 'github_commit',
            'external_id' => basename(rtrim($url, '/')),
            'name' => ($repo['full_name'] ?? $repository).' · '.basename(rtrim($url, '/')),
            'url' => $url,
            'metadata_json' => ['repository' => $repo['full_name'] ?? $repository],
        ]);
    }

    public function createZoomMeeting(IntegrationConnection $connection, Workspace $workspace, ScheduleEvent $event, string $topic): IntegrationLink
    {
        $meeting = $this->request($connection)->post(config('services.zoom.api_url').'/users/me/meetings', [
            'topic' => $topic,
            'type' => 2,
            'start_time' => $event->start_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'duration' => max(1, $event->start_at->diffInMinutes($event->end_at)),
            'timezone' => $event->timezone,
        ])->throw()->json();
        $event->update(['meeting_url' => $meeting['join_url']]);

        return $this->storeLink($connection, $workspace, [
            'schedule_event_id' => $event->id,
            'project_id' => $event->project_id,
            'resource_type' => 'zoom_meeting',
            'external_id' => (string) $meeting['id'],
            'name' => $meeting['topic'] ?? $topic,
            'url' => $meeting['join_url'],
            'metadata_json' => ['start_url' => $meeting['start_url'] ?? null],
        ]);
    }

    private function request(IntegrationConnection $connection): PendingRequest
    {
        if ($connection->refresh_token && $connection->expires_at?->lte(now()->addMinute())) {
            $this->refreshAccessToken($connection);
        }

        return Http::withToken($connection->access_token)->acceptJson();
    }

    private function refreshAccessToken(IntegrationConnection $connection): void
    {
        $config = $this->config($connection->provider);
        $response = match ($connection->provider) {
            IntegrationProvider::SLACK => Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
                'client_id' => $config['client_id'], 'client_secret' => $config['client_secret'],
                'grant_type' => 'refresh_token', 'refresh_token' => $connection->refresh_token,
            ])->throw()->json(),
            IntegrationProvider::GOOGLE_DRIVE => Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $config['client_id'], 'client_secret' => $config['client_secret'],
                'grant_type' => 'refresh_token', 'refresh_token' => $connection->refresh_token,
            ])->throw()->json(),
            IntegrationProvider::GITHUB => Http::asForm()->acceptJson()->post('https://github.com/login/oauth/access_token', [
                'client_id' => $config['client_id'], 'client_secret' => $config['client_secret'],
                'grant_type' => 'refresh_token', 'refresh_token' => $connection->refresh_token,
            ])->throw()->json(),
            IntegrationProvider::ZOOM => Http::asForm()->withBasicAuth($config['client_id'], $config['client_secret'])
                ->post($config['token_url'], ['grant_type' => 'refresh_token', 'refresh_token' => $connection->refresh_token])
                ->throw()->json(),
        };

        if (! isset($response['access_token'])) {
            throw ValidationException::withMessages(['provider' => 'The provider did not return a refreshed access token.']);
        }

        $connection->update([
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? $connection->refresh_token,
            'expires_at' => isset($response['expires_in']) ? now()->addSeconds((int) $response['expires_in']) : null,
            'status' => 'connected',
            'error_message' => null,
        ]);
    }

    private function storeLink(IntegrationConnection $connection, Workspace $workspace, array $data): IntegrationLink
    {
        $link = $connection->links()->create(['workspace_id' => $workspace->id, ...$data]);
        $connection->update(['last_synced_at' => now(), 'status' => 'connected', 'error_message' => null]);

        return $link;
    }

    private function socialite(IntegrationProvider $provider): mixed
    {
        return Socialite::driver(match ($provider) {
            IntegrationProvider::GOOGLE_DRIVE => 'google',
            IntegrationProvider::SLACK => 'slack',
            IntegrationProvider::GITHUB => 'github',
            IntegrationProvider::ZOOM => throw new \LogicException('Zoom uses a direct OAuth2 flow.'),
        });
    }

    private function config(IntegrationProvider $provider): array
    {
        return config('services.'.match ($provider) {
            IntegrationProvider::GOOGLE_DRIVE => 'google',
            default => $provider->value,
        });
    }

    private function signedState(): string
    {
        $value = Str::random(48);

        return $value.'.'.hash_hmac('sha256', $value, config('app.key'));
    }

    private function validState(string $state): bool
    {
        [$value, $signature] = array_pad(explode('.', $state, 2), 2, '');

        return $value !== '' && hash_equals(hash_hmac('sha256', $value, config('app.key')), $signature);
    }
}
