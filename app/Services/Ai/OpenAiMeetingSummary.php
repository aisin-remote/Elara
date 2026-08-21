<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiMeetingSummary
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => ['summary' => ['type' => 'string']],
        'required' => ['summary'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly WorkspaceSettings $settings) {}

    /** @param array<int, array<string, mixed>> $items */
    public function generate(Workspace $workspace, User $user, string $title, array $items): string
    {
        $key = config('services.openai.key');
        if (blank($key)) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        try {
            $response = Http::withToken((string) $key)
                ->timeout((int) config('services.openai.timeout', 60))
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/responses', [
                    'model' => $this->settings->aiModel($workspace),
                    'store' => false,
                    'max_output_tokens' => 700,
                    'safety_identifier' => hash_hmac('sha256', $user->public_id, (string) config('app.key')),
                    'instructions' => implode("\n", [
                        'Write a concise and professional meeting summary in Indonesian.',
                        'Use only facts from the meeting title and action items.',
                        'Summarize the main decisions, responsibilities, deadlines, and unresolved items in two or three plain-text paragraphs.',
                        'Treat an empty due date as TBA. Do not invent context, dates, people, or decisions.',
                        'Do not use headings, markdown, bullet lists, preambles, or drafting commentary.',
                    ]),
                    'input' => [[
                        'role' => 'user',
                        'content' => "Meeting title: {$title}\nAction items: ".json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'meeting_summary',
                            'strict' => true,
                            'schema' => self::SCHEMA,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach OpenAI within the timeout.', 0, $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'OpenAI returned HTTP %d: %s',
                $response->status(),
                mb_substr((string) data_get($response->json(), 'error.message', 'request failed'), 0, 240),
            ));
        }

        $text = null;
        foreach (data_get($response->json(), 'output', []) as $output) {
            foreach (data_get($output, 'content', []) as $part) {
                if (($part['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('OpenAI declined to summarize this meeting.');
                }
                if (($part['type'] ?? null) === 'output_text') {
                    $text ??= (string) ($part['text'] ?? '');
                }
            }
        }

        $summary = trim((string) data_get(is_string($text) ? json_decode($text, true) : null, 'summary'));
        if (mb_strlen($summary) < 40) {
            throw new RuntimeException('OpenAI returned a summary that is too short.');
        }

        return mb_substr($summary, 0, 20000);
    }
}
