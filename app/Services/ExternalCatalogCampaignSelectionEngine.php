<?php

namespace App\Services;

use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogCampaignSelection;
use App\Models\ExternalCatalogItem;
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
                ? $at->copy()->setTimezone($timezone)
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
            $eligibleItems = $campaign->items
                ->filter(fn (ExternalCatalogItem $item): bool => $item->active && $item->visible)
                ->map(function (ExternalCatalogItem $item) use ($warehouseIds): array {
                    $availability = $item->availability['warehouses'] ?? [];
                    $available = collect($availability)
                        ->filter(fn (array $warehouse): bool => in_array(
                            $warehouse['warehouse_external_id'] ?? null,
                            $warehouseIds,
                            true,
                        ))
                        ->sum(fn (array $warehouse): int => max(0, (int) ($warehouse['available_quantity'] ?? 0)));

                    return ['item' => $item, 'available' => $available];
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

                DB::table('external_catalog_campaign_item')
                    ->where('campaign_id', $campaign->id)
                    ->where('external_catalog_item_id', $item->id)
                    ->update([
                        'last_selected_at' => $now,
                        'selection_count' => DB::raw('selection_count + 1'),
                        'updated_at' => now(),
                    ]);
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
            $leftAt = $left['item']->pivot->last_selected_at;
            $rightAt = $right['item']->pivot->last_selected_at;

            if ($leftAt === null && $rightAt !== null) {
                return -1;
            }

            if ($leftAt !== null && $rightAt === null) {
                return 1;
            }

            if ($leftAt !== null && $rightAt !== null && $leftAt != $rightAt) {
                return $leftAt <=> $rightAt;
            }

            if ($strategy === 'round_robin') {
                return ((int) $left['item']->pivot->sort_order) <=> ((int) $right['item']->pivot->sort_order);
            }

            return $left['item']->id <=> $right['item']->id;
        })->values();
    }
}
