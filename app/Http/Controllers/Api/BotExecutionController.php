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
    public function start(Request $request, PublicationExecutionService $service): JsonResponse
    {
        $validated = $request->validate([
            'publication_id' => ['required', 'integer', 'exists:publications,id'],
            'occurrence_id' => ['required', 'string', 'max:120'],
            'channel' => ['required', 'string', 'in:whatsapp,facebook'],
            'target_key' => ['required', 'string', 'max:160'],
            'facebook_group_id' => ['nullable', 'integer', 'exists:facebook_groups,id'],
            'instance_id' => ['nullable', 'string', 'max:191'],
        ]);

        $instanceId = $this->instanceId($request, $validated);
        $authorizedAccountIds = $request->attributes->get('bot_agent_social_account_ids');

        $this->assertAgentOwnsTarget($validated, $instanceId, $authorizedAccountIds);

        $executionAttributes = $validated;
        unset($executionAttributes['instance_id']);
        $executionAttributes['agent_instance_id'] = $instanceId;

        $execution = $service->getOrCreate($executionAttributes);

        $this->assertExecutionAgent($execution, $instanceId, $authorizedAccountIds);

        if (in_array($execution->status, ['succeeded', 'running', 'unknown'], true)) {
            return response()->json(['data' => $execution]);
        }

        $execution = $service->start($execution);

        return response()->json(['data' => $execution]);
    }

    public function complete(
        Request $request,
        int $execution,
        PublicationExecutionService $service,
    ): JsonResponse {
        $execution = PublicationExecution::query()->findOrFail($execution);

        $validated = $request->validate([
            'external_id' => ['nullable', 'string', 'max:255'],
            'instance_id' => ['nullable', 'string', 'max:191'],
        ]);

        $instanceId = $this->instanceId($request, $validated);
        $this->assertExecutionAgent(
            $execution,
            $instanceId,
            $request->attributes->get('bot_agent_social_account_ids'),
        );

        return response()->json([
            'data' => $service->succeed(
                $execution,
                $validated['external_id'] ?? null,
            ),
        ]);
    }

    public function fail(
        Request $request,
        int $execution,
        PublicationExecutionService $service,
    ): JsonResponse {
        $execution = PublicationExecution::query()->findOrFail($execution);

        $validated = $request->validate([
            'error' => ['required', 'string', 'max:2000'],
            'instance_id' => ['nullable', 'string', 'max:191'],
        ]);

        $instanceId = $this->instanceId($request, $validated);
        $this->assertExecutionAgent(
            $execution,
            $instanceId,
            $request->attributes->get('bot_agent_social_account_ids'),
        );

        return response()->json([
            'data' => $service->fail(
                $execution,
                $validated['error'],
            ),
        ]);
    }

    private function instanceId(Request $request, array $validated): ?string
    {
        $instanceId = $request->attributes->get('bot_agent_instance_id')
            ?? $validated['instance_id']
            ?? null;

        return filled($instanceId) ? (string) $instanceId : null;
    }

    private function assertAgentOwnsTarget(array $data, ?string $instanceId, ?array $authorizedAccountIds): void
    {
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
                $authorizedAccountIds === null && $instanceId === $legacyInstanceId,
                403,
                'El destino no está vinculado a una cuenta autorizada para este agente.',
            );

            return;
        }

        if (is_array($authorizedAccountIds)) {
            abort_unless(
                in_array((int) $group->social_account_id, $authorizedAccountIds, true),
                403,
                'El token de este agente no tiene permiso sobre la cuenta social del destino.',
            );
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
        ?array $authorizedAccountIds,
    ): void {
        if (is_array($authorizedAccountIds)) {
            abort_unless(
                $execution->agent_instance_id !== null && $execution->agent_instance_id === $instanceId,
                403,
                'La ejecución no pertenece a esta instancia de agente.',
            );

            $group = $this->executionTarget($execution);

            abort_unless(
                $group !== null
                    && $group->social_account_id !== null
                    && in_array((int) $group->social_account_id, $authorizedAccountIds, true),
                403,
                'El token de este agente no tiene permiso sobre la cuenta de la ejecución.',
            );

            return;
        }

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

    private function executionTarget(PublicationExecution $execution): WhatsAppGroup|FacebookGroup|null
    {
        if ($execution->channel === 'whatsapp') {
            return WhatsAppGroup::query()
                ->where(function ($query) use ($execution): void {
                    $query->where('whatsapp_id', $execution->target_key)
                        ->orWhere('name', $execution->target_key);
                })
                ->first();
        }

        if ($execution->channel === 'facebook') {
            return $execution->facebook_group_id
                ? FacebookGroup::query()->find($execution->facebook_group_id)
                : FacebookGroup::query()->where('facebook_id', $execution->target_key)->first();
        }

        return null;
    }
}
