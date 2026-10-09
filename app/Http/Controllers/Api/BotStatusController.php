<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotStatusController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'platform' => ['required', 'string', 'max:30'],
            'status' => ['required', 'string', 'in:online,offline,error,starting,stopping'],
            'version' => ['nullable', 'string', 'max:100'],
            'host' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ]);

        $botStatus = BotStatus::query()->updateOrCreate(
            ['name' => $validated['name']],
            [
                ...$validated,
                'last_seen_at' => now(),
            ],
        );

        return response()->json([
            'data' => $botStatus->fresh(),
        ]);
    }
}
