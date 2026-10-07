<?php

namespace App\Services;

use App\Models\PublicationDestination;
use Illuminate\Support\Collection;

class PublicationDestinationService
{
    /**
     * Sincroniza los destinos conocidos por un bot.
     *
     * @param array<int, array<string, mixed>> $destinations
     */
    public function sync(string $platform, array $destinations): Collection
    {
        $platform = strtolower(trim($platform));

        abort_unless(
            in_array($platform, ['whatsapp', 'facebook'], true),
            422,
            'Plataforma no soportada.'
        );

        $now = now();
        $ids = [];

        foreach ($destinations as $destination) {
            $externalId = trim((string) ($destination['external_id'] ?? ''));

            if ($externalId === '') {
                continue;
            }

            $record = PublicationDestination::updateOrCreate(
                [
                    'platform' => $platform,
                    'external_id' => $externalId,
                ],
                [
                    'name' => trim((string) ($destination['name'] ?? $externalId)),
                    'url' => $destination['url'] ?? null,
                    'active' => true,
                    'last_synced_at' => $now,
                    'metadata' => $destination['metadata'] ?? null,
                ]
            );

            $ids[] = $record->id;
        }

        /*
         * Los grupos que ya no aparecen en la sincronización
         * dejan de estar disponibles, pero no se eliminan.
         */
        PublicationDestination::query()
            ->where('platform', $platform)
            ->whereNotIn('id', $ids)
            ->update([
                'active' => false,
            ]);

        return PublicationDestination::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }
}
