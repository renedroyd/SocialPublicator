<?php

namespace App\Services;

use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogCampaignSelection;
use App\Models\ExternalCatalogItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExternalCatalogCampaignSelectionEngine
{
    /**
     * Select one eligible group for a campaign. This records a plan only;
     * it never sends content to Facebook or WhatsApp.
     *
     * @return Collection<int, ExternalCatalogCampaignSelection>
     */
    public function selectNextGroup(
        ExternalCatalogCampaign $campaign,
        ?CarbonInterface $at = null,
    ): Collection {
        return DB::transaction(function () use ($campaign, $at): Collection {
            $campaign = ExternalCatalogCampaign::query()
                ->with(['items', 'warehouses'])
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            if (! $campaign->is_active || $campaign->warehouses->isEmpty()) {
                return collect();
            }

            $timezone = $campaign->timezone ?: config('app.timezone', 'UTC');
            $localNow = $at
                ? Carbon::instance($at->toDateTime())->setTimezone($timezone)
                : now($timezone);

            if (! $this->isInsidePostingWindow($campaign, $localNow)) {
                return collect();
            }

            $now = $localNow->copy()->utc();

            if (
                $campaign->last_selected_at
                && $campaign->last_selected_at->gt($now->copy()->subMinutes($campaign->minimum_interval_minutes))
            ) {
                return collect();
            }

            $dayStart = $localNow->copy()->startOfDay()->utc();
            $dayEnd = $localNow->copy()->endOfDay()->utc();
            $dailyGroups = ExternalCatalogCampaignSelection::query()
                ->where('campaign_id', $campaign->id)
                ->whereBetween('selected_at', [$dayStart, $dayEnd])
                ->distinct()
                ->count('selection_group_id');

            if ($dailyGroups >= $campaign->max_publications_per_day) {
                return collect();
            }

            $warehouseIds = $campaign->warehouses->pluck('external_id')->all();
            $items = $campaign->selection_mode === 'automatic'
                ? ExternalCatalogItem::query()
                    ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
                    ->where('active', true)
                    ->where('visible', true)
                    ->orderBy('name')
                    ->get()
                : $campaign->items->filter(
                    fn (ExternalCatalogItem $item): bool => $item->active && $item->visible
                )->values();

            if ($items->isEmpty()) {
                return collect();
            }

            $lastSelectedByItem = ExternalCatalogCampaignSelection::query()
                ->where('campaign_id', $campaign->id)
                ->whereIn('external_catalog_item_id', $items->modelKeys())
                ->selectRaw('external_catalog_item_id, MAX(selected_at) as last_selected_at')
                ->groupBy('external_catalog_item_id')
                ->get()
                ->keyBy('external_catalog_item_id');

            $eligibleItems = $items
                ->map(function (ExternalCatalogItem $item) use ($warehouseIds, $lastSelectedByItem): array {
                    $availability = $item->availability['warehouses'] ?? [];
                    $available = collect($availability)
                        ->filter(fn (array $warehouse): bool => in_array(
                            $warehouse['warehouse_external_id'] ?? null,
                            $warehouseIds,
                            true,
                        ))
                        ->sum(fn (array $warehouse): int => max(0, (int) ($warehouse['available_quantity'] ?? 0)));

                    return [
                        'item' => $item,
                        'available' => $available,
                        'last_selected_at' => $lastSelectedByItem->get($item->id)?->last_selected_at,
                        'sort_order' => (int) ($item->pivot?->sort_order ?? $item->id),
                    ];
                })
                ->filter(fn (array $entry): bool => $entry['available'] > 0)
                ->values();

            if ($eligibleItems->isEmpty()) {
                return collect();
            }

            $eligibleItems = $this->rotate($eligibleItems, $campaign->rotation_strategy);
            $group = $eligibleItems->take(max(1, $campaign->group_size));
            $groupId = (string) Str::uuid();
            $selections = collect();

            foreach ($group as $entry) {
                /** @var ExternalCatalogItem $item */
                $item = $entry['item'];

                $selections->push(ExternalCatalogCampaignSelection::query()->create([
                    'campaign_id' => $campaign->id,
                    'external_catalog_item_id' => $item->id,
                    'selection_group_id' => $groupId,
                    'available_quantity' => $entry['available'],
                    'selected_at' => $now,
                    'status' => 'planned',
                ]));

                if ($campaign->selection_mode === 'manual') {
                    DB::table('external_catalog_campaign_item')
                        ->where('campaign_id', $campaign->id)
                        ->where('external_catalog_item_id', $item->id)
                        ->update([
                            'last_selected_at' => $now,
                            'selection_count' => DB::raw('selection_count + 1'),
                            'updated_at' => now(),
                        ]);
                }
            }

            $campaign->forceFill(['last_selected_at' => $now])->save();

            return $selections;
        });
    }

    private function isInsidePostingWindow(ExternalCatalogCampaign $campaign, CarbonInterface $localNow): bool
    {
        $weekdays = collect($campaign->posting_weekdays ?? [1, 2, 3, 4, 5, 6, 7])
            ->map(fn ($day): int => (int) $day);

        if (! $weekdays->contains($localNow->dayOfWeekIso)) {
            return false;
        }

        $currentTime = $localNow->format('H:i:s');

        return $currentTime >= $campaign->posting_window_start
            && $currentTime <= $campaign->posting_window_end;
    }

    private function rotate(Collection $items, string $strategy): Collection
    {
        if ($strategy === 'random') {
            return $items->shuffle();
        }

        return $items->sort(function (array $left, array $right) use ($strategy): int {
            $leftAt = $left['last_selected_at'] ? Carbon::parse($left['last_selected_at']) : null;
            $rightAt = $right['last_selected_at'] ? Carbon::parse($right['last_selected_at']) : null;

            if ($leftAt === null && $rightAt !== null) {
                return -1;
            }

            if ($leftAt !== null && $rightAt === null) {
                return 1;
            }

            if ($leftAt !== null && $rightAt !== null && ! $leftAt->equalTo($rightAt)) {
                return $leftAt <=> $rightAt;
            }

            if ($strategy === 'round_robin') {
                return $left['sort_order'] <=> $right['sort_order'];
            }

            return $left['item']->id <=> $right['item']->id;
        })->values();
    }
}
