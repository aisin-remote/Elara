<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller extends BaseController
{
    protected function success(Request $request, mixed $data, string $message, string $redirect, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['data' => $data, 'message' => $message], $status);
        }

        $returnTo = $request->string('return_to')->toString();

        if (str_starts_with($returnTo, '/app/') && ! str_starts_with($returnTo, '//')) {
            $redirect = $returnTo;
        }

        return redirect($redirect)->with('status', $message);
    }
}
