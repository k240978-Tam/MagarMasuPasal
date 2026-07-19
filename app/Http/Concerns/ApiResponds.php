<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * The one response shape every /api/v1 controller uses — see
 * docs/architecture/06-api-design.md §6.1. A future mobile client only
 * ever has to learn `{data, meta, links}` once, across every endpoint.
 */
trait ApiResponds
{
    protected function respond(mixed $data, array $meta = [], array $links = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        if ($links !== []) {
            $payload['links'] = $links;
        }

        return response()->json($payload, $status);
    }
}
