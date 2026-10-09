<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublicationExecution;
use App\Services\PublicationExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotExecutionController extends Controller
{
    public function start(
        Request $request,
        PublicationExecutionService $service
    ): JsonResponse {
        $validated = $request->validate([
            'publication_id' => ['required', 'integer', 'exists:publications,id'],
            'occurrence_id' => ['required', 'string', 'max:120'],
            'channel' => ['required', 'string', 'in:whatsapp,facebook'],
            'target_key' => ['required', 'string', 'max:160'],
            'facebook_group_id' => ['nullable', 'integer', 'exists:facebook_groups,id'],
        ]);

        $execution = $service->getOrCreate($validated);

        // An unknown external result must never be silently restarted: the post
        // may already exist on the social network.
        if (in_array($execution->status, ['succeeded', 'running', 'unknown'], true)) {
            return response()->json(['data' => $execution]);
        }

        $execution = $service->start($execution);

        return response()->json(['data' => $execution]);
    }

    public function complete(
        Request $request,
        PublicationExecution $execution,
        PublicationExecutionService $service
    ): JsonResponse {
        $validated = $request->validate([
            'external_id' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'data' => $service->succeed(
                $execution,
                $validated['external_id'] ?? null,
            ),
        ]);
    }

    public function fail(
        Request $request,
        PublicationExecution $execution,
        PublicationExecutionService $service
    ): JsonResponse {
        $validated = $request->validate([
            'error' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json([
            'data' => $service->fail(
                $execution,
                $validated['error'],
            ),
        ]);
    }
}
