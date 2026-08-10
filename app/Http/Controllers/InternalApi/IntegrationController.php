<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Integration\ConnectIntegration;
use App\Actions\Integration\DisconnectIntegration;
use App\Enums\IntegrationProvider;
use App\Http\Requests\Integration\DisconnectIntegrationRequest;
use App\Http\Requests\Integration\IntegrationCallbackRequest;
use App\Http\Requests\Integration\IntegrationMutationRequest;
use App\Http\Requests\Integration\IntegrationRedirectRequest;
use App\Models\IntegrationConnection;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\IntegrationService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class IntegrationController extends Controller
{
    public function redirect(IntegrationRedirectRequest $request, IntegrationProvider $provider, IntegrationService $integrations): RedirectResponse
    {
        $workspace = Workspace::where('public_id', $request->string('workspace_public_id'))
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $request->user()->id)->where('status', 'active'))
            ->firstOrFail();
        $this->authorize('connect', [IntegrationConnection::class, $workspace]);
        $config = config('services.'.($provider === IntegrationProvider::GOOGLE_DRIVE ? 'google' : $provider->value));
        abort_unless($config['client_id'] && $config['client_secret'], 409, 'OAuth credentials are not configured for this provider.');

        return $integrations->redirect($provider, $workspace);
    }

    public function callback(IntegrationCallbackRequest $request, IntegrationProvider $provider, IntegrationService $integrations, ConnectIntegration $connect): RedirectResponse
    {
        if ($request->filled('error')) {
            $request->session()->forget('integration.oauth');

            return redirect()->route('settings.integrations')->withErrors(['provider' => 'The provider denied access: '.$request->string('error')]);
        }

        try {
            $credentials = $integrations->callback($provider, $request->string('code')->toString());
        } catch (GuzzleException|RequestException) {
            throw ValidationException::withMessages(['provider' => 'The provider could not complete authorization. Please reconnect and try again.']);
        }
        $workspace = Workspace::findOrFail($credentials['workspace_id']);
        $this->authorize('connect', [IntegrationConnection::class, $workspace]);
        $connect->handle($workspace, $request->user(), $provider, $credentials, $request->ip());

        return redirect()->route('app.settings.integrations', $workspace)->with('status', $provider->label().' connected.');
    }

    public function action(IntegrationMutationRequest $request, IntegrationConnection $connection, IntegrationService $integrations): JsonResponse|RedirectResponse
    {
        $workspace = $connection->workspace;
        $data = $request->validated();

        try {
            $link = match ($connection->provider) {
                IntegrationProvider::SLACK => tap(null, fn () => $integrations->sendSlack($connection, $data['channel'], $data['message'])),
                IntegrationProvider::GOOGLE_DRIVE => $integrations->linkDriveFile($connection, $workspace, $this->project($workspace, $data['project_public_id']), $data['file_id']),
                IntegrationProvider::GITHUB => $integrations->linkGithubResource($connection, $workspace, $this->task($workspace, $data['task_public_id']), $data['repository'], $data['url']),
                IntegrationProvider::ZOOM => $integrations->createZoomMeeting($connection, $workspace, $this->event($workspace, $data['schedule_event_public_id']), $data['topic']),
            };
        } catch (RequestException $exception) {
            $connection->update(['status' => in_array($exception->response->status(), [401, 403], true) ? 'revoked' : 'error', 'error_message' => 'The provider request failed. Reconnect and try again.']);
            throw ValidationException::withMessages(['provider' => 'The provider request failed. Reconnect and try again.']);
        }

        return $this->success($request, $link ? ['public_id' => $link->public_id, 'url' => $link->url] : null, 'Integration action completed.', route('app.settings.integrations', $workspace));
    }

    public function destroy(DisconnectIntegrationRequest $request, IntegrationConnection $connection, DisconnectIntegration $disconnect): JsonResponse|RedirectResponse
    {
        try {
            $disconnect->handle($connection, $request->user(), $request->ip());
        } catch (RequestException) {
            $connection->update(['status' => 'error', 'error_message' => 'Provider revocation failed. The local connection was kept so you can retry safely.']);
            throw ValidationException::withMessages(['provider' => 'Provider revocation failed. Please retry.']);
        }

        return $this->success($request, null, 'Integration disconnected.', route('app.settings.integrations', $connection->workspace));
    }

    private function project(Workspace $workspace, string $publicId): Project
    {
        return $workspace->projects()->where('public_id', $publicId)->firstOrFail();
    }

    private function task(Workspace $workspace, string $publicId): Task
    {
        return $workspace->tasks()->where('public_id', $publicId)->firstOrFail();
    }

    private function event(Workspace $workspace, string $publicId): ScheduleEvent
    {
        return $workspace->scheduleEvents()->where('public_id', $publicId)->firstOrFail();
    }
}
