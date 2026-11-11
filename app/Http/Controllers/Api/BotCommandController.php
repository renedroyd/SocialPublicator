<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotCommand;
use App\Services\BotCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotCommandController extends Controller
{
    public function claim(Request $request, BotCommandService $service): JsonResponse
    {
        $validated = $request->validate([
            'bot_name' => ['required', 'string', 'max:100'],
            'instance_id' => ['nullable', 'string', 'max:191'],
        ]);

        $command = $service->claim(
            $validated['bot_name'],
            $this->instanceId($request, $validated),
            $request->attributes->get('bot_agent_social_account_ids'),
        );

        return response()->json(['data' => $command]);
    }

    public function complete(
        Request $request,
        BotCommand $command,
        BotCommandService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'bot_name' => ['required', 'string', 'max:100'],
            'instance_id' => ['nullable', 'string', 'max:191'],
            'result' => ['nullable', 'array'],
        ]);

        $command = $service->complete(
            $command,
            $validated['bot_name'],
            $validated['result'] ?? [],
            $this->instanceId($request, $validated),
            $request->attributes->get('bot_agent_social_account_ids'),
        );

        return response()->json(['data' => $command]);
    }

    public function fail(
        Request $request,
        BotCommand $command,
        BotCommandService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'bot_name' => ['required', 'string', 'max:100'],
            'instance_id' => ['nullable', 'string', 'max:191'],
            'error' => ['required', 'string', 'max:2000'],
            'retry' => ['nullable', 'boolean'],
        ]);

        $command = $service->fail(
            $command,
            $validated['bot_name'],
            $validated['error'],
            $validated['retry'] ?? true,
            $this->instanceId($request, $validated),
            $request->attributes->get('bot_agent_social_account_ids'),
        );

        return response()->json(['data' => $command]);
    }

    private function instanceId(Request $request, array $validated): ?string
    {
        $instanceId = $request->attributes->get('bot_agent_instance_id')
            ?? $validated['instance_id']
            ?? null;

        return filled($instanceId) ? (string) $instanceId : null;
    }
}
