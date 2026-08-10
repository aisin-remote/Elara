<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\ProcessStripeWebhook;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends WebhookController
{
    public function handleWebhook(Request $request): Response
    {
        $processor = app(ProcessStripeWebhook::class);
        $receipt = $processor->reserve($request->all());
        if (! $processor->claim($receipt)) {
            return response('Webhook already received.', 200);
        }

        try {
            $response = parent::handleWebhook($request);
            if ($response->isSuccessful()) {
                $receipt->update(['processed_at' => now(), 'processing_at' => null]);
            } else {
                $receipt->update(['processing_at' => null]);
            }
        } catch (\Throwable $exception) {
            $receipt->update(['processing_at' => null]);
            throw $exception;
        }

        return $response;
    }
}
