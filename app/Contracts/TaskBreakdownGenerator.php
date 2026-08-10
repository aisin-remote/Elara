<?php

namespace App\Contracts;

use App\Exceptions\TaskBreakdownFailed;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;

/**
 * Turns a request into a proposed task list. One implementation today (OpenAI); swapping
 * provider is a new class and a config value, not a rewrite.
 */
interface TaskBreakdownGenerator
{
    /**
     * @param  string|null  $note  A reviewer's instruction when regenerating, e.g. "smaller tasks".
     * @return array{
     *     provider: string,
     *     model: string,
     *     tasks: array<int, array{
     *         title: string, description: string, estimate_minutes: int,
     *         depends_on: array<int, int>, requires_user_validation: bool,
     *         validation_reason: string|null
     *     }>,
     *     input_tokens: int|null,
     *     output_tokens: int|null,
     * }
     *
     * @throws TaskBreakdownFailed On a refusal, an API error, a timeout, or missing credentials.
     */
    public function generate(FeatureRequest|ProjectRequest $request, ?string $note = null): array;
}
