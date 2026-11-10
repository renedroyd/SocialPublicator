<?php

use App\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tenantTables = [
        'categories',
        'products',
        'inventory_movements',
        'publications',
        'publication_executions',
        'publication_schedules',
        'publication_destinations',
        'facebook_groups',
        'whatsapp_groups',
        'external_catalog_items',
        'external_catalog_warehouses',
        'external_catalog_campaigns',
        'external_catalog_campaign_selections',
        'external_catalog_campaign_triggers',
        'integration_event_inbox',
        'bot_commands',
        'bot_statuses',
    ];

    private array $pivotTables = [
        'publication_product' => ['publication_id', 'publications', 'product_id', 'products'],
        'publication_publication_destination' => ['publication_id', 'publications', 'publication_destination_id', 'publication_destinations'],
        'facebook_group_publication' => ['publication_id', 'publications', 'facebook_group_id', 'facebook_groups'],
        'whatsapp_group_publication' => ['publication_id', 'publications', 'whatsapp_group_id', 'whatsapp_groups'],
        'external_catalog_campaign_item' => ['campaign_id', 'external_catalog_campaigns', 'external_catalog_item_id', 'external_catalog_items'],
        'external_catalog_campaign_warehouse' => ['campaign_id', 'external_catalog_campaigns', 'external_catalog_warehouse_id', 'external_catalog_warehouses'],
        'external_catalog_campaign_whatsapp_group' => ['campaign_id', 'external_catalog_campaigns', 'whatsapp_group_id', 'whatsapp_groups'],
        'external_catalog_campaign_facebook_group' => ['campaign_id', 'external_catalog_campaigns', 'facebook_group_id', 'facebook_groups'],
    ];

    public function up(): void
    {
        $workspaceId = $this->ensurePrimaryWorkspace();

        foreach (array_keys($this->pivotTables) as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')
                    ->nullable()
                    ->constrained('workspaces')
                    ->cascadeOnDelete();
            });
        }

        foreach ($this->tenantTables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')
                    ->nullable()
                    ->constrained('workspaces')
                    ->cascadeOnDelete();
            });
        }

        foreach ($this->tenantTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                DB::table($tableName)
                    ->whereNull('workspace_id')
                    ->update(['workspace_id' => $workspaceId]);
            }
        }

        $this->backfillDependentRows();
        $this->backfillPivotTables();
        $this->replaceGlobalUniqueIndexes();

        foreach ($this->tenantTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->index('workspace_id', $tableName.'_workspace_id_index');
                });
            }
        }

        foreach (array_keys($this->pivotTables) as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'workspace_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->index('workspace_id', $tableName.'_workspace_id_index');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->pivotTables) as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'workspace_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->dropIndex($tableName.'_workspace_id_index');
                    $table->dropConstrainedForeignId('workspace_id');
                });
            }
        }

        foreach (array_reverse($this->tenantTables) as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'workspace_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->dropIndex($tableName.'_workspace_id_index');
                    $table->dropConstrainedForeignId('workspace_id');
                });
            }
        }

        // Per-workspace unique indexes intentionally remain in place. Restoring
        // global uniqueness could fail after tenants have created duplicate values.
    }

    private function ensurePrimaryWorkspace(): int
    {
        $workspace = Workspace::query()->first();

        if (! $workspace) {
            $firstUser = DB::table('users')->orderBy('id')->first();

            $workspace = Workspace::query()->create([
                'name' => 'Espacio de trabajo principal',
                'slug' => 'principal-'.Str::lower(Str::random(8)),
                'created_by_user_id' => $firstUser?->id,
                'settings' => ['legacy_migration' => true],
            ]);
        }

        $firstUser = DB::table('users')->orderBy('id')->first();

        if ($firstUser) {
            foreach (DB::table('users')->orderBy('id')->get() as $index => $user) {
                DB::table('workspace_memberships')->updateOrInsert(
                    [
                        'workspace_id' => $workspace->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'role' => $index === 0 ? 'owner' : 'admin',
                        'status' => 'active',
                        'joined_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        return (int) $workspace->id;
    }

    private function backfillDependentRows(): void
    {
        $relations = [
            'inventory_movements' => ['producto_id', 'products'],
            'publication_executions' => ['publication_id', 'publications'],
            'publication_schedules' => ['publication_id', 'publications'],
            'external_catalog_campaign_selections' => ['campaign_id', 'external_catalog_campaigns'],
            'external_catalog_campaign_triggers' => ['campaign_id', 'external_catalog_campaigns'],
        ];

        foreach ($relations as $tableName => [$foreignKey, $parentTable]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasTable($parentTable)) {
                continue;
            }

            foreach (DB::table($tableName)->whereNull('workspace_id')->get() as $row) {
                $workspaceId = DB::table($parentTable)
                    ->where('id', $row->{$foreignKey})
                    ->value('workspace_id');

                if (! $workspaceId) {
                    throw new RuntimeException("Cannot assign a workspace to {$tableName} row {$row->id}.");
                }

                DB::table($tableName)->where('id', $row->id)->update([
                    'workspace_id' => $workspaceId,
                ]);
            }
        }
    }

    private function backfillPivotTables(): void
    {
        foreach ($this->pivotTables as $tableName => [$leftKey, $leftTable, $rightKey, $rightTable]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            foreach (DB::table($tableName)->whereNull('workspace_id')->get() as $row) {
                $leftWorkspace = DB::table($leftTable)->where('id', $row->{$leftKey})->value('workspace_id');
                $rightWorkspace = DB::table($rightTable)->where('id', $row->{$rightKey})->value('workspace_id');

                if (! $leftWorkspace || ! $rightWorkspace || (int) $leftWorkspace !== (int) $rightWorkspace) {
                    throw new RuntimeException("Cross-workspace relation detected in {$tableName} row {$row->id}.");
                }

                DB::table($tableName)->where('id', $row->id)->update([
                    'workspace_id' => $leftWorkspace,
                ]);
            }
        }
    }

    private function replaceGlobalUniqueIndexes(): void
    {
        $indexes = [
            'categories' => [
                ['drop' => 'categories_nombre_unique', 'columns' => ['workspace_id', 'nombre'], 'name' => 'categories_workspace_nombre_unique'],
            ],
            'products' => [
                ['drop' => 'products_sku_unique', 'columns' => ['workspace_id', 'sku'], 'name' => 'products_workspace_sku_unique'],
            ],
            'facebook_groups' => [
                ['drop' => 'facebook_groups_facebook_id_unique', 'columns' => ['workspace_id', 'facebook_id'], 'name' => 'facebook_groups_workspace_facebook_id_unique'],
            ],
            'whatsapp_groups' => [
                ['drop' => 'whatsapp_groups_whatsapp_id_unique', 'columns' => ['workspace_id', 'whatsapp_id'], 'name' => 'whatsapp_groups_workspace_whatsapp_id_unique'],
            ],
            'publication_destinations' => [
                ['drop' => 'publication_destinations_platform_external_id_unique', 'columns' => ['workspace_id', 'platform', 'external_id'], 'name' => 'publication_destinations_workspace_platform_external_id_unique'],
            ],
            'external_catalog_items' => [
                ['drop' => 'external_catalog_items_source_external_id_unique', 'columns' => ['workspace_id', 'source', 'external_id'], 'name' => 'external_catalog_items_workspace_source_external_id_unique'],
            ],
            'external_catalog_warehouses' => [
                ['drop' => 'external_catalog_warehouses_source_external_id_unique', 'columns' => ['workspace_id', 'source', 'external_id'], 'name' => 'external_catalog_warehouses_workspace_source_external_id_unique'],
            ],
            'integration_event_inbox' => [
                ['drop' => 'integration_event_inbox_event_id_unique', 'columns' => ['workspace_id', 'event_id'], 'name' => 'integration_event_inbox_workspace_event_id_unique'],
            ],
        ];

        foreach ($indexes as $tableName => $definitions) {
            foreach ($definitions as $definition) {
                Schema::table($tableName, function (Blueprint $table) use ($definition): void {
                    $table->dropUnique($definition['drop']);
                    $table->unique($definition['columns'], $definition['name']);
                });
            }
        }
    }
};
