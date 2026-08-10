<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\SendAiMessageRequest;
use App\Models\AiConversation;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\Ai\OpenAiAssistant;
use App\Services\WorkspaceSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AiMessageController extends Controller
{
    public function store(
        SendAiMessageRequest $request,
        Workspace $workspace,
        WorkspaceSettings $settings,
        OpenAiAssistant $assistant,
    ): StreamedResponse {
        $user = $request->user();
        $conversation = DB::transaction(function () use ($request, $workspace, $settings, $user): AiConversation {
            if ($request->filled('conversation_public_id')) {
                $conversation = AiConversation::query()
                    ->where('public_id', $request->string('conversation_public_id'))
                    ->where('workspace_id', $workspace->id)
                    ->where('user_id', $user->id)
                    ->firstOrFail();
                $this->authorize('view', $conversation);
            } else {
                $project = null;
                if ($request->filled('project_public_id')) {
                    $project = Project::query()
                        ->visibleTo($user)
                        ->where('workspace_id', $workspace->id)
                        ->where('public_id', $request->string('project_public_id'))
                        ->firstOrFail();
                }

                $conversation = AiConversation::create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'project_id' => $project?->id,
                    'title' => Str::limit($request->string('message')->toString(), 60, ''),
                    'model' => $settings->aiModel($workspace),
                ]);
            }

            $conversation->messages()->create([
                'role' => 'user',
                'body' => $request->string('message')->toString(),
            ]);

            return $conversation;
        });

        return response()->stream(function () use ($assistant, $conversation, $workspace): void {
            $send = function (string $event, array $payload): void {
                echo 'event: '.$event."\n";
                echo 'data: '.json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $send('conversation', [
                'public_id' => $conversation->public_id,
                'title' => $conversation->title,
                'url' => route('app.ai.show', [$workspace, $conversation]),
            ]);

            try {
                $message = $assistant->respond($conversation, fn (string $delta) => $send('delta', ['text' => $delta]));
                $send('done', [
                    'message_public_id' => $message->public_id,
                    'model' => $message->model,
                    'input_tokens' => $message->input_tokens,
                    'output_tokens' => $message->output_tokens,
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $send('error', [
                    'message' => app()->isLocal()
                        ? $exception->getMessage()
                        : 'Ask AI could not finish this answer. Please try again.',
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
