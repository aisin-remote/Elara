<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Every way a breakdown can fail for reasons outside our code: a refusal, an API error, a
 * timeout, a malformed response, or missing credentials. Its own type so the job can catch
 * precisely this and let genuine bugs surface instead of swallowing them.
 */
class TaskBreakdownFailed extends RuntimeException
{
    public static function refused(string $reason): self
    {
        return new self('The model declined to produce a breakdown: '.$reason);
    }

    public static function notConfigured(): self
    {
        return new self('The breakdown provider is not configured. Set OPENAI_API_KEY to enable it.');
    }
}
