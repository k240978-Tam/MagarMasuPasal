<?php

namespace Modules\POS\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\POS\Models\IdempotencyKey;

/**
 * A retried POST with the same Idempotency-Key header replays the stored
 * response instead of re-running $handler — the guarantee the offline-sync
 * POS outbox needs: a mobile client that times out mid-request and resends
 * the exact same finalize-sale call must never create two sales.
 */
trait HandlesIdempotency
{
    protected function withIdempotency(Request $request, callable $handler): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return response()->json(['message' => 'The Idempotency-Key header is required.'], 400);
        }

        $businessId = $request->user()->business_id;

        $existing = IdempotencyKey::where('key', $key)->first();

        if ($existing) {
            return response()->json($existing->response_body, $existing->response_status);
        }

        $response = $handler();

        IdempotencyKey::create([
            'business_id' => $businessId,
            'key' => $key,
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getData(true),
        ]);

        return $response;
    }
}
