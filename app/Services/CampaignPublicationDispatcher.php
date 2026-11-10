<?php

namespace App\Services;

use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogCampaignSelection;
use App\Models\ExternalCatalogCampaignTrigger;
use App\Models\ExternalCatalogItem;
use App\Models\Publication;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceMembershipDestinationPermission;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CampaignPublicationDispatcher
{
    public function __construct(
        private readonly DubaiShopApiClient $client,
        private readonly ExternalCatalogSyncService $catalogSync,
    ) {}

    /**
     * Convert pending campaign plans and triggers into records consumed by the existing bot API.
     *
     * @return array{triggers:int,selections:int,skipped:int,errors:int}
     */
    public function dispatchPending(int $limit = 100): array
    {
        $counts = ['triggers' => 0, 'selections' => 0, 'skipped' => 0, 'blocked' => 0, 'errors' => 0];

        $triggers = ExternalCatalogCampaignTrigger::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($triggers as $trigger) {
            try {
                $result = $this->dispatchTrigger($trigger);

                if ($result === 'queued') {
                    $counts['triggers']++;
                } elseif ($result === 'skipped') {
                    $counts['skipped']++;
                } elseif ($result === 'blocked') {
                    $counts['blocked']++;
                }
            } catch (Throwable $exception) {
                $trigger->update(['last_error' => $exception->getMessage()]);
                $counts['errors']++;
                Log::warning('Campaign trigger dispatch failed and will be retried.', [
                    'trigger_id' => $trigger->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $groups = ExternalCatalogCampaignSelection::query()
            ->where('status', 'planned')
            ->select(['campaign_id', 'selection_group_id'])
            ->distinct()
            ->orderBy('campaign_id')
            ->limit($limit)
            ->get();

        foreach ($groups as $group) {
            try {
                $result = $this->dispatchSelectionGroup($group->campaign_id, $group->selection_group_id);

                if ($result === 'queued') {
                    $counts['selections']++;
                } elseif ($result === 'skipped') {
                    $counts['skipped']++;
                } elseif ($result === 'blocked') {
                    $counts['blocked']++;
                }
            } catch (Throwable $exception) {
                ExternalCatalogCampaignSelection::query()
                    ->where('campaign_id', $group->campaign_id)
                    ->where('selection_group_id', $group->selection_group_id)
                    ->where('status', 'planned')
                    ->update(['last_error' => $exception->getMessage()]);

                $counts['errors']++;
                Log::warning('Campaign selection dispatch failed and will be retried.', [
                    'campaign_id' => $group->campaign_id,
                    'selection_group_id' => $group->selection_group_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $counts;
    }

    private function dispatchTrigger(ExternalCatalogCampaignTrigger $trigger): string
    {
        $trigger->loadMissing(['campaign.warehouses', 'campaign.whatsappGroups', 'campaign.facebookGroups', 'item']);
        $campaign = $trigger->campaign;

        if (! $campaign || ! $campaign->is_active) {
            $trigger->update(['last_error' => 'La campaña está inactiva; el aviso queda pendiente.']);

            return 'blocked';
        }

        if (! $this->hasDestinations($campaign)) {
            $trigger->update(['last_error' => 'Configura al menos un grupo activo para cada canal seleccionado.']);

            return 'blocked';
        }

        $payload = $this->client->item($trigger->item->external_id);

        if ($payload === null) {
            $this->skipTrigger($trigger, 'El artículo ya no existe en Dubai Shop.');

            return 'skipped';
        }

        $item = $this->catalogSync->upsertItem($payload);

        if (! $item->active || ! $item->visible) {
            $this->skipTrigger($trigger, 'El artículo ya no está publicado y visible en Dubai Shop.');

            return 'skipped';
        }

        $available = $this->availableInCampaignWarehouses($campaign, $item);

        if ($available === null) {
            $trigger->update(['last_error' => 'No hay almacenes activos habilitados para abastecimiento online.']);

            return 'blocked';
        }

        if ($trigger->trigger_type === 'catalog.item_out_of_stock' && $available > 0) {
            $this->skipTrigger($trigger, 'El artículo volvió a tener existencias antes del envío.');

            return 'skipped';
        }

        if ($trigger->trigger_type === 'catalog.item_restocked' && $available <= 0) {
            $this->skipTrigger($trigger, 'La reposición dejó de estar disponible antes del envío.');

            return 'skipped';
        }

        if ($trigger->trigger_type === 'price.changed') {
            $expectedPrice = $trigger->payload['current_price'] ?? null;
            $currentPrice = [
                'amount_minor' => $item->price_amount_minor,
                'currency' => $item->currency,
            ];

            if ($expectedPrice !== $currentPrice) {
                $this->skipTrigger($trigger, 'El precio cambió nuevamente; se espera el evento más reciente.');

                return 'skipped';
            }
        }

        $text = $this->triggerText($trigger->trigger_type, $item);
        $this->createPublication(
            $campaign,
            $text,
            $item->image_url,
            ['external_campaign_trigger_id' => $trigger->id],
        );

        $trigger->update([
            'status' => 'queued',
            'last_error' => null,
        ]);

        return 'queued';
    }

    private function dispatchSelectionGroup(int $campaignId, string $groupId): string
    {
        $selections = ExternalCatalogCampaignSelection::query()
            ->where('campaign_id', $campaignId)
            ->where('selection_group_id', $groupId)
            ->where('status', 'planned')
            ->with(['campaign.warehouses', 'campaign.whatsappGroups', 'campaign.facebookGroups', 'item'])
            ->get();

        if ($selections->isEmpty()) {
            return 'skipped';
        }

        $campaign = $selections->first()->campaign;

        if (! $campaign || ! $campaign->is_active) {
            $this->blockSelections($selections, 'La campaña está inactiva; la selección queda pendiente.');

            return 'blocked';
        }

        if (! $this->hasDestinations($campaign)) {
            $this->blockSelections($selections, 'Configura al menos un grupo activo para cada canal seleccionado.');

            return 'blocked';
        }

        $items = collect();

        foreach ($selections as $selection) {
            $payload = $this->client->item($selection->item->external_id);

            if ($payload === null) {
                $selection->update(['status' => 'skipped', 'last_error' => 'El artículo ya no existe en Dubai Shop.']);

                continue;
            }

            $item = $this->catalogSync->upsertItem($payload);

            if (! $item->active || ! $item->visible) {
                $selection->update(['status' => 'skipped', 'last_error' => 'El artículo ya no está publicado y visible.']);

                continue;
            }

            $available = $this->availableInCampaignWarehouses($campaign, $item);

            if ($available === null) {
                $selection->update(['last_error' => 'No hay almacenes activos habilitados para abastecimiento online.']);

                continue;
            }

            if ($available <= 0) {
                $selection->update(['status' => 'skipped', 'last_error' => 'No quedan existencias en los almacenes de esta campaña.']);

                continue;
            }

            $items->push(['item' => $item, 'available' => $available, 'selection' => $selection]);
        }

        if ($items->isEmpty()) {
            return 'skipped';
        }

        $text = $this->selectionText($items);
        $publication = $this->createPublication(
            $campaign,
            $text,
            $items->first()['item']->image_url,
            ['external_selection_group_id' => $groupId],
        );

        foreach ($items as $entry) {
            $entry['selection']->update([
                'status' => 'queued',
                'last_error' => null,
            ]);
        }

        return 'queued';
    }

    private function createPublication(
        ExternalCatalogCampaign $campaign,
        string $text,
        ?string $imageUrl,
        array $uniqueOrigin,
    ): Publication {
        $scheduledAt = now()->addMinute();

        return DB::transaction(function () use ($campaign, $text, $imageUrl, $uniqueOrigin, $scheduledAt): Publication {
            $publication = Publication::query()->firstOrCreate(
                $uniqueOrigin,
                [
                    'external_campaign_id' => $campaign->id,
                    'created_by_user_id' => $campaign->created_by_user_id,
                    'date' => $scheduledAt->toDateString(),
                    'time' => $scheduledAt->format('H:i'),
                    'image' => $this->safeImageUrl($imageUrl),
                    'text' => $text,
                    'destination' => $campaign->destination,
                    'active' => true,
                    'recurrence_type' => 'none',
                ],
            );

            [$authorizedWhatsAppGroups, $authorizedFacebookGroups] = $this->authorizedGroups($campaign);
            $whatsappGroupIds = in_array($campaign->destination, ['whatsapp', 'both'], true)
                ? $authorizedWhatsAppGroups->modelKeys()
                : [];
            $facebookGroupIds = in_array($campaign->destination, ['facebook', 'both'], true)
                ? $authorizedFacebookGroups->modelKeys()
                : [];

            $publication->whatsappGroups()->sync($whatsappGroupIds);
            $publication->facebookGroups()->sync($facebookGroupIds);

            return $publication;
        });
    }

    private function hasDestinations(ExternalCatalogCampaign $campaign): bool
    {
        [$whatsappGroups, $facebookGroups] = $this->authorizedGroups($campaign);

        $hasWhatsApp = ! in_array($campaign->destination, ['whatsapp', 'both'], true)
            || $whatsappGroups->isNotEmpty();
        $hasFacebook = ! in_array($campaign->destination, ['facebook', 'both'], true)
            || $facebookGroups->isNotEmpty();

        return $hasWhatsApp && $hasFacebook;
    }

    /**
     * @return array{0: Collection, 1: Collection}
     */
    private function authorizedGroups(ExternalCatalogCampaign $campaign): array
    {
        $whatsappGroups = $campaign->whatsappGroups->where('active', true);
        $facebookGroups = $campaign->facebookGroups->where('active', true);

        if ($campaign->created_by_user_id === null) {
            return [$whatsappGroups, $facebookGroups];
        }

        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->where('user_id', $campaign->created_by_user_id)
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            return [collect(), collect()];
        }

        $context = app(WorkspaceContext::class);
        $previousWorkspaceId = $context->id();
        $context->set((int) $campaign->workspace_id);

        try {
            $whatsappGroups = $whatsappGroups
                ->filter(fn ($group): bool => $membership->canPublishToDestination(
                    WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP,
                    (int) $group->id,
                ))
                ->values();

            $facebookGroups = $facebookGroups
                ->filter(fn ($group): bool => $membership->canPublishToDestination(
                    WorkspaceMembershipDestinationPermission::DESTINATION_FACEBOOK_GROUP,
                    (int) $group->id,
                ))
                ->values();
        } finally {
            if ($previousWorkspaceId !== null) {
                $context->set($previousWorkspaceId);
            } else {
                $context->clear();
            }
        }

        return [$whatsappGroups, $facebookGroups];
    }

    private function availableInCampaignWarehouses(ExternalCatalogCampaign $campaign, ExternalCatalogItem $item): ?int
    {
        $warehouseIds = $campaign->warehouses
            ->filter(fn ($warehouse): bool => $warehouse->active && $warehouse->online_fulfillment_enabled)
            ->pluck('external_id')
            ->all();

        if ($warehouseIds === []) {
            return null;
        }

        return collect($item->availability['warehouses'] ?? [])
            ->filter(fn (array $warehouse): bool => in_array(
                $warehouse['warehouse_external_id'] ?? null,
                $warehouseIds,
                true,
            ))
            ->sum(fn (array $warehouse): int => max(0, (int) ($warehouse['available_quantity'] ?? 0)));
    }

    private function triggerText(string $triggerType, ExternalCatalogItem $item): string
    {
        $price = $this->formatPrice($item);

        return match ($triggerType) {
            'catalog.item_out_of_stock' => "⚠️ PRODUCTO AGOTADO\n\n{$item->name}\n\nGracias por tu interés. Te avisaremos cuando vuelva a estar disponible.",
            'catalog.item_restocked' => "🟢 ¡DISPONIBLE NUEVAMENTE!\n\n{$item->name}\nPrecio: {$price}\n\n📩 Escríbenos para realizar tu pedido.",
            'price.changed' => "💰 ACTUALIZACIÓN DE PRECIO\n\n{$item->name}\nNuevo precio: {$price}\n\n📩 Escríbenos para realizar tu pedido.",
            default => $item->name,
        };
    }

    private function selectionText($items): string
    {
        $lines = ['🔥 PRODUCTOS DESTACADOS', ''];

        foreach ($items as $entry) {
            $lines[] = '• '.$entry['item']->name.' — '.$this->formatPrice($entry['item']);
        }

        $lines[] = '';
        $lines[] = '📩 Escríbenos para realizar tu pedido.';

        return implode("\n", $lines);
    }

    private function formatPrice(ExternalCatalogItem $item): string
    {
        return number_format(((int) $item->price_amount_minor) / 100, 2, '.', ',').' '.$item->currency;
    }

    private function safeImageUrl(?string $imageUrl): ?string
    {
        if (! $imageUrl || ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(parse_url($imageUrl, PHP_URL_SCHEME), ['http', 'https'], true)
            ? $imageUrl
            : null;
    }

    private function skipTrigger(ExternalCatalogCampaignTrigger $trigger, string $reason): void
    {
        $trigger->update(['status' => 'skipped', 'last_error' => $reason]);
    }

    private function blockSelections($selections, string $reason): void
    {
        foreach ($selections as $selection) {
            $selection->update(['last_error' => $reason]);
        }
    }

    private function skipSelections($selections, string $reason): void
    {
        foreach ($selections as $selection) {
            $selection->update(['status' => 'skipped', 'last_error' => $reason]);
        }
    }
}
