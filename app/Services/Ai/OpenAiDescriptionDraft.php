<?php

namespace App\Services\Ai;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiDescriptionDraft
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'description' => ['type' => 'string'],
        ],
        'required' => ['description'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly WorkspaceSettings $settings) {}

    public function generate(
        Workspace $workspace,
        User $user,
        string $kind,
        string $name,
        string $brief,
        ?Project $system = null,
    ): string {
        $key = config('services.openai.key');
        if (blank($key)) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $model = $this->settings->aiModel($workspace);

        try {
            $response = Http::withToken((string) $key)
                ->timeout((int) config('services.openai.timeout', 60))
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/responses', [
                    'model' => $model,
                    'store' => false,
                    'max_output_tokens' => 900,
                    'safety_identifier' => hash_hmac('sha256', $user->public_id, (string) config('app.key')),
                    'instructions' => $this->instructions($kind),
                    'input' => [[
                        'role' => 'user',
                        'content' => $this->prompt($kind, $name, $brief, $system),
                    ]],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'description_draft',
                            'strict' => true,
                            'schema' => self::SCHEMA,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach OpenAI within the timeout.', 0, $exception);
        }

        if ($response->failed()) {
            $message = (string) data_get($response->json(), 'error.message', 'request failed');

            throw new RuntimeException(sprintf(
                'OpenAI returned HTTP %d: %s',
                $response->status(),
                mb_substr($message, 0, 240),
            ));
        }

        $description = $this->extractDescription($response->json());

        return mb_substr($description, 0, 5000);
    }

    private function instructions(string $kind): string
    {
        return implode("\n", [
            "You expand a short {$kind} brief for an internal IT delivery team.",
            'Write in the same language as the brief.',
            'Return two to four concise plain-text paragraphs suitable for a description field.',
            'Clarify the current problem or goal, scope, intended users or stakeholders, expected result, important constraints or assumptions, and measurable completion signals.',
            'Preserve every fact supplied by the user. Do not invent dates, budgets, vendors, people, integrations, or technology choices.',
            'Do not use headings, markdown, preambles, or commentary about the drafting process.',
        ]);
    }

    private function prompt(string $kind, string $name, string $brief, ?Project $system): string
    {
        return implode("\n", array_filter([
            ucfirst($kind).' name: '.$name,
            $system ? 'System: '.$system->name : null,
            'Short brief: '.$brief,
        ]));
    }

    /** @param array<string, mixed> $body */
    private function extractDescription(array $body): string
    {
        $text = null;

        foreach (data_get($body, 'output', []) as $item) {
            foreach (data_get($item, 'content', []) as $part) {
                if (($part['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('OpenAI declined to expand this description.');
                }

                if (($part['type'] ?? null) === 'output_text') {
                    $text ??= (string) ($part['text'] ?? '');
                }
            }
        }

        $decoded = is_string($text) ? json_decode($text, true) : null;
        $description = trim((string) data_get($decoded, 'description'));

        if (mb_strlen($description) < 80) {
            throw new RuntimeException('OpenAI returned a description that is too short.');
        }

        return $description;
    }
}
