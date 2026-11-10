<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Publication;
use App\Models\SocialAccountConnection;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceMembershipDestinationPermission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BotPublicationController extends Controller
{
    public function index(?Request $request = null): JsonResponse
    {
        $request ??= request();
        $validated = $request->validate([
            'instance_id' => ['nullable', 'string', 'max:191'],
        ]);
        $instanceId = filled($validated['instance_id'] ?? null)
            ? $validated['instance_id']
            : (string) config('services.bot_api.command_bot_name', 'WBot');
        $now = now();
        $currentDate = $now->toDateString();

        $publications = Publication::query()
            ->with([
                'whatsappGroups.socialAccount.connections',
                'facebookGroups.socialAccount.connections',
                'whatsappGroups' => function ($query): void {
                    $query
                        ->where('active', true)
                        ->orderBy('name');
                },
                'facebookGroups' => function ($query): void {
                    $query
                        ->where('active', true)
                        ->orderBy('name');
                },
            ])
            ->where('active', true)
            ->whereDate('date', '<=', $currentDate)
            ->where(function ($query) use ($currentDate): void {
                $query
                    ->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $currentDate);
            })
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        $result = [];

        foreach ($publications as $publication) {
            $membership = null;

            if ($publication->created_by_user_id !== null) {
                $membership = WorkspaceMembership::query()
                    ->where('workspace_id', $publication->workspace_id)
                    ->where('user_id', $publication->created_by_user_id)
                    ->where('status', 'active')
                    ->first();

                if ($membership === null) {
                    continue;
                }
            }

            $eligibleWhatsAppGroups = $membership === null
                ? $publication->whatsappGroups
                : $publication->whatsappGroups->filter(fn ($group): bool => $membership->canPublishToDestination(
                    WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP,
                    (int) $group->id,
                ))->values();
            $eligibleFacebookGroups = $membership === null
                ? $publication->facebookGroups
                : $publication->facebookGroups->filter(fn ($group): bool => $membership->canPublishToDestination(
                    WorkspaceMembershipDestinationPermission::DESTINATION_FACEBOOK_GROUP,
                    (int) $group->id,
                ))->values();

            if ($instanceId !== null) {
                $legacyInstanceId = (string) config('services.bot_api.command_bot_name', 'WBot');
                $eligibleWhatsAppGroups = $eligibleWhatsAppGroups
                    ->filter(fn ($group): bool => $this->groupBelongsToAgent(
                        $group,
                        $instanceId,
                        $legacyInstanceId,
                        SocialAccountConnection::TYPE_WPP_CONNECT_QR,
                    ))
                    ->values();
                $eligibleFacebookGroups = $eligibleFacebookGroups
                    ->filter(fn ($group): bool => $this->groupBelongsToAgent(
                        $group,
                        $instanceId,
                        $legacyInstanceId,
                        SocialAccountConnection::TYPE_BROWSER_SESSION,
                    ))
                    ->values();
            }

                if ($eligibleWhatsAppGroups->isEmpty() && $eligibleFacebookGroups->isEmpty()) {
                    continue;
                }
            }

            foreach ($this->generateDailyOccurrences($publication, $now) as $occurrence) {
                $whatsappGroups = in_array(
                    $publication->destination,
                    ['whatsapp', 'both'],
                    true
                )
                    ? $eligibleWhatsAppGroups
                        ->map(static fn ($group): array => [
                            'id' => $group->id,
                            'whatsapp_id' => $group->whatsapp_id,
                            'name' => $group->name,
                        ])
                        ->values()
                        ->all()
                    : [];

                $facebookGroups = in_array(
                    $publication->destination,
                    ['facebook', 'both'],
                    true
                )
                    ? $eligibleFacebookGroups
                        ->map(static fn ($group): array => [
                            'id' => $group->id,
                            'facebook_id' => $group->facebook_id,
                            'name' => $group->name,
                            'url' => $group->url,
                        ])
                        ->values()
                        ->all()
                    : [];

                $occurrenceId = $publication->id.'_'.$occurrence->format('Y-m-d_H-i');
                $imageUrl = $publication->image
                    ? (str_starts_with($publication->image, 'https://') || str_starts_with($publication->image, 'http://')
                        ? $publication->image
                        : url(Storage::url($publication->image)))
                    : null;

                $result[] = [
                    'id' => $publication->id,
                    'occurrence_id' => $occurrenceId,
                    'date' => $occurrence->format('Y-m-d'),
                    'time' => $occurrence->format('H:i'),
                    'image' => $imageUrl,
                    'text' => $publication->text,
                    'destination' => $publication->destination,
                    'recurrence_type' => $publication->recurrence_type,
                    'recurrence_days' => $publication->recurrence_days,
                    'interval_hours' => $publication->interval_hours,
                    'interval_minutes' => $publication->interval_minutes ?? ($publication->interval_hours ? $publication->interval_hours * 60 : null),
                    'recurrence_start_time' => $publication->recurrence_start_time,
                    'recurrence_end_time' => $publication->recurrence_end_time,
                    'end_date' => $publication->end_date?->format('Y-m-d'),
                    'whatsapp_groups' => $whatsappGroups,
                    'whatsappGroups' => $whatsappGroups,
                    'facebook_groups' => $facebookGroups,

                    // Legacy fields kept temporarily for the stable WBot baseline.
                    'ocurrencia_id' => $occurrenceId,
                    'fecha' => $occurrence->format('Y-m-d'),
                    'hora' => $occurrence->format('H:i'),
                    'imagen' => $imageUrl,
                    'texto' => $publication->text,
                    'destino' => $publication->destination === 'both'
                        ? 'ambos'
                        : $publication->destination,
                    'tipo_recurrencia' => match ($publication->recurrence_type) {
                        'daily' => 'diaria',
                        'weekly' => 'semanal',
                        'hourly' => 'cada_horas',
                        default => 'ninguna',
                    },
                    'intervalo_horas' => $publication->interval_hours,
                    'intervalo_minutos' => $publication->interval_minutes ?? ($publication->interval_hours ? $publication->interval_hours * 60 : null),
                    'dias_recurrencia' => $publication->recurrence_days,
                    'hora_inicio_recurrencia' => $publication->recurrence_start_time,
                    'hora_fin_recurrencia' => $publication->recurrence_end_time,
                    'fecha_fin' => $publication->end_date?->format('Y-m-d'),
                    'facebookGrupos' => collect($facebookGroups)
                        ->map(static fn (array $group): array => [
                            'id' => $group['id'],
                            'facebook_id' => $group['facebook_id'],
                            'nombre' => $group['name'],
                            'url' => $group['url'],
                        ])
                        ->values()
                        ->all(),
                ];
            }
        }

        usort(
            $result,
            static fn (array $a, array $b): int => strcmp(
                $a['date'].' '.$a['time'],
                $b['date'].' '.$b['time']
            )
        );

        return response()->json(['data' => $result]);
    }

    private function groupBelongsToAgent(
        $group,
        string $instanceId,
        string $legacyInstanceId,
        string $connectionType,
    ): bool {
        if (! $group->social_account_id) {
            return $instanceId === $legacyInstanceId;
        }

        return $group->socialAccount?->connections?->contains(
            fn (SocialAccountConnection $connection): bool => $connection->connection_type === $connectionType
                && $connection->status === 'connected'
                && $connection->agent_instance_id === $instanceId,
        ) ?? false;
    }

    private function generateDailyOccurrences(
        Publication $publication,
        Carbon $now
    ): array {
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $now->copy()->endOfDay();
        $startDate = Carbon::parse($publication->date)->startOfDay();

        if ($publication->recurrence_type === 'none') {
            if (! $startDate->isSameDay($now)) {
                return [];
            }

            $dateTime = Carbon::parse(
                $publication->date->format('Y-m-d').' '.substr((string) $publication->time, 0, 5)
            );

            return $dateTime->betweenIncluded($dayStart, $dayEnd)
                ? [$dateTime]
                : [];
        }

        if ($dayStart->lt($startDate)) {
            return [];
        }

        if (
            $publication->end_date &&
            $dayStart->gt(Carbon::parse($publication->end_date)->endOfDay())
        ) {
            return [];
        }

        if ($publication->recurrence_type === 'weekly') {
            $days = collect($publication->recurrence_days ?? [])
                ->map(static fn ($day): int => (int) $day)
                ->filter(static fn (int $day): bool => $day >= 1 && $day <= 7)
                ->unique()
                ->values()
                ->all();

            if ($days === []) {
                return [];
            }

            if (! in_array($now->dayOfWeekIso, $days, true)) {
                return [];
            }

            $time = substr((string) $publication->time, 0, 5);

            if ($time === '') {
                return [];
            }

            return [Carbon::parse($now->format('Y-m-d').' '.$time)];
        }

        if ($publication->recurrence_type === 'daily') {
            $time = substr((string) $publication->time, 0, 5);

            if ($time === '') {
                return [];
            }

            return [Carbon::parse($now->format('Y-m-d').' '.$time)];
        }

        if ($publication->recurrence_type !== 'hourly') {
            return [];
        }

        $intervalMinutes = (int) ($publication->interval_minutes ?? (($publication->interval_hours ?? 0) * 60));

        if ($intervalMinutes < 1 || empty($publication->recurrence_start_time)) {
            return [];
        }

        $startTime = substr((string) $publication->recurrence_start_time, 0, 5);
        $endTime = ! empty($publication->recurrence_end_time)
            ? substr((string) $publication->recurrence_end_time, 0, 5)
            : '23:59';

        $start = Carbon::parse($now->format('Y-m-d').' '.$startTime);
        $end = Carbon::parse($now->format('Y-m-d').' '.$endTime);

        if ($start->gt($end)) {
            return [];
        }

        $occurrences = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $occurrences[] = $current->copy();
            $current->addMinutes($intervalMinutes);
        }

        return $occurrences;
    }
}
