<?php

namespace App\Actions\Billing;

use App\Models\WebhookReceipt;
use Illuminate\Validation\ValidationException;

class ProcessStripeWebhook
{
    public function reserve(array $payload): WebhookReceipt
    {
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $receipt = WebhookReceipt::firstOrCreate(
            ['provider' => 'stripe', 'external_id' => (string) $payload['id']],
            ['payload_hash' => $hash],
        );

        if (! hash_equals($receipt->payload_hash, $hash)) {
            throw ValidationException::withMessages(['webhook' => 'The event identifier was reused with a different payload.']);
        }

        return $receipt;
    }

    public function claim(WebhookReceipt $receipt): bool
    {
        return WebhookReceipt::whereKey($receipt->id)
            ->whereNull('processed_at')
            ->where(fn ($query) => $query->whereNull('processing_at')->orWhere('processing_at', '<', now()->subMinutes(5)))
            ->update(['processing_at' => now()]) === 1;
    }
}
