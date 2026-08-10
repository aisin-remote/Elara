<?php

namespace App\Services\Planning;

use App\Enums\WorkspaceRole;
use App\Models\DeliveryInsight;
use App\Models\User;
use App\Models\Workspace;
use App\Services\NotificationPreferenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeeklyInsightsGenerator
{
    public function __construct(
        private readonly PortfolioService $portfolio,
        private readonly NotificationPreferenceService $notifications,
    ) {}

    public function generate(Workspace $workspace, ?CarbonImmutable $anchor = null): DeliveryInsight
    {
        $anchor ??= CarbonImmutable::now($workspace->timezone ?: config('app.timezone'));
        $periodStart = $anchor->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
        $periodEnd = $anchor->endOfWeek(CarbonImmutable::SUNDAY)->toDateString();

        $existing = DeliveryInsight::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->first();

        if ($existing) {
            return $existing;
        }

        $owner = $workspace->memberships()
            ->active()
            ->where('role', WorkspaceRole::OWNER->value)
            ->with('user')
            ->first()
            ?->user;

        $viewer = $owner ?? $workspace->memberships()->active()->with('user')->first()?->user;

        if (! $viewer instanceof User) {
            throw new \RuntimeException('Workspace has no active members for insight generation.');
        }

        $portfolio = $this->portfolio->forWorkspace($workspace, $viewer);
        $payload = [
            'summary' => $portfolio['summary'],
            'projects' => collect($portfolio['projects'])->map(fn (array $row) => [
                'name' => $row['name'],
                'type' => $row['type'],
                'forecast' => $row['forecast']['state'],
                'progress' => $row['forecast']['progress'],
                'blocked' => $row['forecast']['blocked'],
                'critical' => $row['forecast']['critical'],
                'reason' => $row['forecast']['reason'],
            ])->all(),
        ];

        [$summary, $source] = $this->summarize($workspace, $payload);

        $insight = DeliveryInsight::query()->create([
            'workspace_id' => $workspace->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'summary' => $summary,
            'payload' => $payload,
            'source' => $source,
            'generated_at' => now(),
        ]);

        $recipients = $workspace->memberships()
            ->active()
            ->whereIn('role', [
                WorkspaceRole::OWNER->value,
                WorkspaceRole::ADMIN->value,
                WorkspaceRole::MANAGER->value,
                WorkspaceRole::SUPERVISOR->value,
            ])
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($recipients as $recipient) {
            $this->notifications->notify(
                $recipient,
                $workspace,
                'project_updated',
                'Weekly delivery insight',
                $summary,
                route('app.portfolio.index', $workspace),
                ['insight_public_id' => $insight->public_id, 'period_start' => $periodStart],
            );
        }

        return $insight;
    }

    /** @param  array<string, mixed>  $payload */
    private function summarize(Workspace $workspace, array $payload): array
    {
        $rules = $this->ruleSummary($workspace, $payload);
        $apiKey = config('services.openai.key');

        if (! filled($apiKey)) {
            return [$rules, 'rules'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(45)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('orbitra.ai.ask_model', config('services.openai.model', 'gpt-4.1-mini')),
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => 'You write concise weekly delivery digests for an IT project workspace. Use only the JSON facts. 3-5 short sentences. No markdown.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
                        ],
                    ],
                    'store' => false,
                ]);

            if ($response->successful()) {
                $text = data_get($response->json(), 'output.0.content.0.text')
                    ?? data_get($response->json(), 'output_text');
                if (is_string($text) && trim($text) !== '') {
                    return [trim($text), 'openai'];
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Weekly insights OpenAI fallback', ['workspace' => $workspace->public_id, 'error' => $exception->getMessage()]);
        }

        return [$rules, 'rules'];
    }

    /** @param  array<string, mixed>  $payload */
    private function ruleSummary(Workspace $workspace, array $payload): string
    {
        $summary = $payload['summary'];
        $late = collect($payload['projects'])->where('forecast', 'late')->pluck('name');
        $risk = collect($payload['projects'])->where('forecast', 'at_risk')->pluck('name');

        $parts = [
            $workspace->name.' has '.$summary['projects'].' active deliveries this week: '
                .$summary['on_track'].' on track, '.$summary['at_risk'].' at risk, '.$summary['late'].' late.',
            'Across the portfolio there are '.$summary['blocked_tasks'].' blocked tasks and '
                .$summary['critical_tasks'].' tasks on a critical path.',
        ];

        if ($late->isNotEmpty()) {
            $parts[] = 'Late: '.$late->take(5)->join(', ').'.';
        }
        if ($risk->isNotEmpty()) {
            $parts[] = 'At risk: '.$risk->take(5)->join(', ').'.';
        }

        return implode(' ', $parts);
    }
}
