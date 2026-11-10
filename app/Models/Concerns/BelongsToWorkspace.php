<?php

namespace App\Models\Concerns;

use App\Models\Category;
use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogCampaignTrigger;
use App\Models\FacebookGroup;
use App\Models\Product;
use App\Models\Publication;
use App\Models\PublicationExecution;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait BelongsToWorkspace
{
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder): void {
            $workspaceId = app(WorkspaceContext::class)->id();

            if ($workspaceId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('workspace_id'),
                $workspaceId,
            );
        });

        static::saving(function (Model $model): void {
            $workspaceId = app(WorkspaceContext::class)->id();
            $relations = match ($model::class) {
                Product::class => [
                    ['categoria_id', Category::class],
                ],
                Publication::class => [
                    ['external_campaign_id', ExternalCatalogCampaign::class],
                    ['external_campaign_trigger_id', ExternalCatalogCampaignTrigger::class],
                ],
                PublicationExecution::class => [
                    ['facebook_group_id', FacebookGroup::class],
                ],
                default => [],
            };

            foreach ($relations as [$foreignKey, $relatedClass]) {
                $relatedId = $model->getAttribute($foreignKey);

                if ($relatedId === null) {
                    continue;
                }

                $related = (new $relatedClass)->newQuery()
                    ->withoutGlobalScope('workspace')
                    ->find($relatedId);

                if ($related === null || (int) $related->workspace_id !== (int) $workspaceId) {
                    throw new LogicException('Cross-workspace relation is not allowed for '.class_basename($model).'.');
                }
            }
        });

        static::creating(function (Model $model): void {
            $context = app(WorkspaceContext::class);
            $workspaceId = $context->id();

            if ($workspaceId === null) {
                throw new LogicException('A workspace context is required to create '.class_basename($model).'.');
            }

            $model->setAttribute('workspace_id', $workspaceId);
        });
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeWithoutWorkspaceScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('workspace');
    }
}
