<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacebookGroup;
use App\Models\PublicationExecution;
use App\Models\SocialAccountConnection;
use App\Models\WhatsAppGroup;
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
            'instance_id' => ['nullable', 'string', 'max:191'],
        ]);

        $this->assertAgentOwnsTarget($validated);

        $executionAttributes = $validated;
        unset($executionAttributes['instance_id']);
        $executionAttributes['agent_instance_id'] = $validated['instance_id'] ?? null;

        $execution = $service->getOrCreate($executionAttributes);

        $this->assertExecutionAgent($execution, $validated['instance_id'] ?? null);

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
            'instance_id' => ['nullable', 'string', 'max:191'],
        ]);

        $this->assertExecutionAgent($execution, $validated['instance_id'] ?? null);

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
            'instance_id' => ['nullable', 'string', 'max:191'],
        ]);

        $this->assertExecutionAgent($execution, $validated['instance_id'] ?? null);

        return response()->json([
            'data' => $service->fail(
                $execution,
                $validated['error'],
            ),
        ]);
    }

    private function assertAgentOwnsTarget(array $data): void
    {
        $instanceId = $data['instance_id'] ?? null;

        $legacyInstanceId = (string) config('services.bot_api.command_bot_name', 'WBot');

        if ($data['channel'] === 'whatsapp') {
            $group = WhatsAppGroup::query()
                ->where(function ($query) use ($data): void {
                    $query->where('whatsapp_id', $data['target_key'])
                        ->orWhere('name', $data['target_key']);
                })
                ->first();
            $connectionType = SocialAccountConnection::TYPE_WPP_CONNECT_QR;
        } else {
            $group = ! empty($data['facebook_group_id'])
                ? FacebookGroup::query()->find($data['facebook_group_id'])
                : FacebookGroup::query()->where('facebook_id', $data['target_key'])->first();
            $connectionType = SocialAccountConnection::TYPE_BROWSER_SESSION;
        }

        if (! $group || ! $group->social_account_id) {
            abort_unless(
                $instanceId === null || $instanceId === $legacyInstanceId,
                403,
                'El destino no está asignado a esta instancia WBot.',
            );

            return;
        }

        abort_unless($instanceId !== null, 403, 'La cuenta de destino requiere una identidad de agente.');

        $connection = SocialAccountConnection::query()
            ->where('social_account_id', $group->social_account_id)
            ->where('connection_type', $connectionType)
            ->where('status', 'connected')
            ->first();

        abort_unless(
            $connection !== null && $connection->agent_instance_id === $instanceId,
            403,
            'La cuenta social de este destino pertenece a otra instancia WBot o no está conectada.',
        );
    }

    private function assertExecutionAgent(
        PublicationExecution $execution,
        ?string $instanceId,
    ): void {
        if ($execution->agent_instance_id !== null) {
            abort_unless(
                $execution->agent_instance_id === $instanceId,
                403,
                'La ejecución pertenece a otra instancia WBot.',
            );

            return;
        }

        $legacyInstanceId = (string) config('services.bot_api.command_bot_name', 'WBot');
        abort_unless(
            $instanceId === null || $instanceId === $legacyInstanceId,
            403,
            'La ejecución histórica pertenece al agente legacy.',
        );
    }
}
