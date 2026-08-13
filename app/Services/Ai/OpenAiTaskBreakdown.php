<?php

namespace App\Services\Ai;

use App\Contracts\TaskBreakdownGenerator;
use App\Enums\TaskStatusCategory;
use App\Exceptions\TaskBreakdownFailed;
use App\Models\Feature;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Services\WorkspaceSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Responses API, called through Laravel's HTTP client. No SDK: this is a single
 * endpoint with a JSON body, and a community package in the path of a core flow buys
 * typed ergonomics at the cost of trailing the API.
 *
 * Output is constrained by a strict JSON schema, so the result is valid by construction
 * rather than by parsing prose.
 */
class OpenAiTaskBreakdown implements TaskBreakdownGenerator
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'tasks' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'estimate_minutes' => ['type' => 'integer'],
                        'checklist' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'minItems' => 2,
                            'maxItems' => 8,
                            'description' => 'Concrete completion steps used to measure task progress.',
                        ],
                        'requires_user_validation' => ['type' => 'boolean'],
                        'validation_reason' => ['type' => ['string', 'null']],
                    ],
                    // strict mode requires every property listed and no extras.
                    'required' => [
                        'title', 'description', 'estimate_minutes', 'checklist',
                        'requires_user_validation', 'validation_reason',
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['tasks'],
        'additionalProperties' => false,
    ];

    public function generate(FeatureRequest|ProjectRequest|Feature|Project $subject, ?string $note = null): array
    {
        $key = config('services.openai.key');

        if (blank($key)) {
            throw TaskBreakdownFailed::notConfigured();
        }

        $model = app(WorkspaceSettings::class)->aiModel($subject->workspace);

        try {
            $response = Http::withToken($key)
                ->timeout((int) config('services.openai.timeout', 60))
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/responses', [
                    'model' => $model,
                    // System context first and byte-stable per system, request-specific text
                    // last: provider-side caching keys off a stable prefix.
                    'input' => array_values(array_filter([
                        ['role' => 'system', 'content' => $this->systemContext($subject)],
                        ['role' => 'user', 'content' => $this->requestText($subject)],
                        blank($note) ? null : ['role' => 'user', 'content' => 'Revise your plan: '.$note],
                    ])),
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'task_breakdown',
                            'strict' => true,
                            'schema' => self::SCHEMA,
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            // Deliberately not $e->getMessage() verbatim into storage: it can carry the full
            // request context. The status is what the reviewer needs.
            throw new TaskBreakdownFailed('Could not reach OpenAI within the timeout.', 0, $e);
        }

        if ($response->failed()) {
            throw new TaskBreakdownFailed(sprintf(
                'OpenAI returned HTTP %d: %s',
                $response->status(),
                (string) data_get($response->json(), 'error.message', 'no error message'),
            ));
        }

        $body = $response->json();

        return [
            'provider' => 'openai',
            'model' => (string) data_get($body, 'model', $model),
            'tasks' => $this->extractTasks($body),
            'input_tokens' => data_get($body, 'usage.input_tokens'),
            'output_tokens' => data_get($body, 'usage.output_tokens'),
        ];
    }

    /**
     * A refusal arrives as a content part of its own instead of schema-conforming output.
     * Reading the text without checking for it crashes on an edge case nobody can reproduce
     * on demand, so the check comes first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractTasks(array $body): array
    {
        $text = null;

        foreach (data_get($body, 'output', []) as $item) {
            foreach (data_get($item, 'content', []) as $part) {
                if (($part['type'] ?? null) === 'refusal') {
                    throw TaskBreakdownFailed::refused((string) ($part['refusal'] ?? 'no reason given'));
                }

                if (($part['type'] ?? null) === 'output_text') {
                    $text ??= (string) ($part['text'] ?? '');
                }
            }
        }

        if ($text === null) {
            throw new TaskBreakdownFailed('OpenAI returned no output text.');
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! is_array($decoded['tasks'] ?? null)) {
            throw new TaskBreakdownFailed('OpenAI returned output that does not match the task schema.');
        }

        return array_values($decoded['tasks']);
    }

    /**
     * Byte-stable for a given system: provider-side caching keys off a stable prefix, and
     * even without it a stable prefix makes two breakdowns for one system comparable.
     */
    private function systemContext(FeatureRequest|ProjectRequest|Feature|Project $subject): string
    {
        $project = match (true) {
            $subject instanceof FeatureRequest => $subject->system,
            $subject instanceof ProjectRequest => $subject->project,
            $subject instanceof Feature => $subject->project,
            default => $subject,
        };

        $lines = [
            'You break a delivery request into concrete tasks for a software team.',
            'Estimate in minutes of focused work. Prefer several small tasks over one large one.',
            'Give every task 2 to 8 concrete checklist items that make completion measurable.',
        ];

        if ($subject instanceof FeatureRequest || $subject instanceof ProjectRequest) {
            $lines[] = 'Set requires_user_validation only for tasks producing something the requester must confirm:';
            $lines[] = 'a visible UI change, a report format, or a migrated dataset. Give a reason when you do.';
        } else {
            $lines[] = 'This work was entered directly by the IT team. Set requires_user_validation to false and validation_reason to null.';
        }

        $lines[] = '';

        if ($project instanceof Project) {
            $lines[] = 'System: '.$project->name;
            $lines[] = 'Description: '.($project->description ?: 'none recorded');

            $recent = $project->tasks()
                ->whereNotNull('completed_at')
                ->orderByDesc('completed_at')
                ->limit(10)
                ->pluck('title');

            if ($recent->isNotEmpty()) {
                $lines[] = 'How this team has broken down work on this system before:';
                foreach ($recent as $title) {
                    $lines[] = '- '.$title;
                }
            }

            $statuses = $project->taskStatuses()
                ->whereNull('archived_at')
                ->where('category', '!=', TaskStatusCategory::CANCELLED->value)
                ->orderBy('position')
                ->pluck('name');

            if ($statuses->isNotEmpty()) {
                $lines[] = 'Workflow statuses in use: '.$statuses->implode(', ');
            }
        }

        return implode("\n", $lines);
    }

    private function requestText(FeatureRequest|ProjectRequest|Feature|Project $subject): string
    {
        if ($subject instanceof FeatureRequest) {
            return implode("\n", [
                'Title: '.$subject->title,
                'Current condition: '.$subject->problem,
                'Target condition: '.$subject->desired_outcome,
                'Benefit: '.$subject->benefit,
                'Urgency: '.$subject->urgency->value,
            ]);
        }

        if ($subject instanceof Feature) {
            return implode("\n", [
                'Feature: '.$subject->name,
                'Description: '.($subject->description ?: 'No description recorded.'),
            ]);
        }

        if ($subject instanceof Project) {
            return implode("\n", [
                'Project: '.$subject->name,
                'Description: '.($subject->description ?: 'No description recorded.'),
                'Planned start: '.($subject->start_date?->format('Y-m-d') ?? 'not set'),
                'Planned due: '.($subject->due_date?->format('Y-m-d') ?? 'not set'),
            ]);
        }

        return implode("\n", [
            'Title: '.$subject->title,
            'Benefit: '.$subject->benefit,
            'Concept: '.$subject->concept,
            'Current business process: '.$subject->business_process,
            'Proposed flow: '.$subject->flow,
        ]);
    }
}
