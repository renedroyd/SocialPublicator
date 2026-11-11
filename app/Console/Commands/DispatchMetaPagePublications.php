<?php

namespace App\Console\Commands;

use App\Jobs\PublishMetaPagePublication;
use App\Models\Publication;
use App\Models\PublicationDestination;
use App\Models\SocialAccount;
use App\Models\SocialAccountConnection;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceMembershipDestinationPermission;
use App\Services\PublicationExecutionService;
use App\Services\Workspaces\WorkspaceContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DispatchMetaPagePublications extends Command
{
    protected $signature = 'meta-pages:dispatch';

    protected $description = 'Dispatch due publications to authorized Facebook Pages through Meta Graph API';

    public function handle(
        PublicationExecutionService $executions,
        WorkspaceContext $context,
    ): int {
        $now = now()->startOfMinute();
        $occurrenceTime = $now->format('H:i');
        $total = 0;
        $errors = 0;

        foreach (Workspace::query()->orderBy('id')->pluck('id') as $workspaceId) {
            $context->set((int) $workspaceId);

            try {
                $total += $this->dispatchForWorkspace($executions, $now, $occurrenceTime);
            } catch (Throwable $exception) {
                $errors++;
                $this->error("Workspace {$workspaceId}: ".$exception->getMessage());
            } finally {
                $context->clear();
            }
        }

        $this->info("Publicaciones de páginas Meta encoladas: {$total}; errores: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function dispatchForWorkspace(
        PublicationExecutionService $executions,
        Carbon $now,
        string $occurrenceTime,
    ): int {
        $count = 0;

        Publication::query()
            ->with(['destinations.socialAccount.connections'])
            ->where('active', true)
            ->whereIn('destination', ['facebook', 'both'])
            ->whereDate('date', '<=', $now->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $now->toDateString()))
            ->chunkById(100, function ($publications) use ($executions, $now, $occurrenceTime, &$count): void {
                foreach ($publications as $publication) {
                    if (! $this->isDueAt($publication, $now, $occurrenceTime)) {
                        continue;
                    }

                    $occurrenceId = $publication->id.'_'.$now->format('Y-m-d_H-i');

                    foreach ($publication->destinations as $destination) {
                        if (! $this->isEligibleDestination($destination) || ! $this->creatorCanPublish($publication, $destination)) {
                            continue;
                        }

                        DB::transaction(function () use ($publication, $destination, $occurrenceId, $executions, &$count): void {
                            $execution = $executions->getOrCreate([
                                'publication_id' => $publication->id,
                                'occurrence_id' => $occurrenceId,
                                'channel' => 'facebook',
                                'target_key' => 'meta_page_destination:'.$destination->id,
                                'agent_instance_id' => null,
                            ]);

                            if ($execution->status !== 'pending') {
                                return;
                            }

                            $execution = $executions->start($execution);
                            PublishMetaPagePublication::dispatch($execution->id)->afterCommit();
                            $count++;
                        });
                    }
                }
            });

        return $count;
    }

    private function isDueAt(Publication $publication, Carbon $now, string $occurrenceTime): bool
    {
        $startDate = Carbon::parse($publication->date)->startOfDay();
        $today = $now->copy()->startOfDay();

        if ($startDate->gt($today)) {
            return false;
        }

        if ($publication->end_date && Carbon::parse($publication->end_date)->endOfDay()->lt($now)) {
            return false;
        }

        $scheduledTime = $publication->recurrence_type === 'hourly'
            ? ($publication->recurrence_start_time ?: $publication->time)
            : $publication->time;
        $scheduledTime = substr((string) $scheduledTime, 0, 5);

        if ($publication->recurrence_type === 'none') {
            return $startDate->equalTo($today) && $scheduledTime === $occurrenceTime;
        }

        if ($publication->recurrence_type === 'daily') {
            return $scheduledTime === $occurrenceTime;
        }

        if ($publication->recurrence_type === 'weekly') {
            $days = collect($publication->recurrence_days ?? [])
                ->map(fn ($day): int => (int) $day)
                ->all();

            return $scheduledTime === $occurrenceTime
                && in_array($now->dayOfWeekIso, $days, true);
        }

        if ($publication->recurrence_type === 'hourly') {
            $interval = max(1, (int) ($publication->interval_minutes ?: (($publication->interval_hours ?: 1) * 60)));
            $endTime = $publication->recurrence_end_time ?: '23:59';
            $startMinute = $this->minutesOfDay((string) $scheduledTime);
            $currentMinute = $this->minutesOfDay($occurrenceTime);
            $endMinute = $this->minutesOfDay($endTime);

            return $currentMinute >= $startMinute
                && $currentMinute <= $endMinute
                && (($currentMinute - $startMinute) % $interval) === 0;
        }

        return false;
    }

    private function minutesOfDay(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($hours * 60) + $minutes;
    }

    private function isEligibleDestination(PublicationDestination $destination): bool
    {
        $account = $destination->socialAccount;

        if (
            $destination->platform !== 'facebook'
            || ! $destination->active
            || ! $account
            || $account->account_type !== SocialAccount::TYPE_FACEBOOK_PAGE
            || $account->status !== SocialAccount::STATUS_CONNECTED
        ) {
            return false;
        }

        return $account->connections->contains(
            fn (SocialAccountConnection $connection): bool => $connection->connection_type === SocialAccountConnection::TYPE_META_OAUTH
                && $connection->provider === 'meta'
                && $connection->status === 'connected'
                && $connection->revoked_at === null
                && (! $connection->expires_at || $connection->expires_at->isFuture())
                && filled($connection->credentials['access_token'] ?? null),
        );
    }

    private function creatorCanPublish(Publication $publication, PublicationDestination $destination): bool
    {
        if ($publication->created_by_user_id === null) {
            return true;
        }

        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $publication->workspace_id)
            ->where('user_id', $publication->created_by_user_id)
            ->where('status', 'active')
            ->first();

        return $membership !== null
            && $membership->canPublishToDestination(
                WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION,
                (int) $destination->id,
            );
    }
}
