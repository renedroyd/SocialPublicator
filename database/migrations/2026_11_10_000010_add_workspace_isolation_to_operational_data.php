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
        $this->enforceWorkspaceOwnership();
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
        $dependentRelations = [
            'inventory_movements' => ['producto_id', 'products'],
            'publication_executions' => ['publication_id', 'publications'],
            'publication_schedules' => ['publication_id', 'publications'],
            'external_catalog_campaign_selections' => [
                'campaign_id' => 'external_catalog_campaigns',
                'external_catalog_item_id' => 'external_catalog_items',
            ],
            'external_catalog_campaign_triggers' => [
                'campaign_id' => 'external_catalog_campaigns',
                'external_catalog_item_id' => 'external_catalog_items',
            ],
        ];

        foreach ($dependentRelations as $childTable => $relations) {
            if (! Schema::hasTable($childTable)) {
                continue;
            }

            $foreignKeys = is_array($relations) && array_is_list($relations)
                ? [$relations[0] => $relations[1]]
                : $relations;

            foreach ($foreignKeys as $foreignKey => $parentTable) {
                $constraint = 'ws_'.substr(hash('sha256', $childTable.'_'.$foreignKey), 0, 10).'_fk';

                Schema::table($childTable, function (Blueprint $table) use ($constraint): void {
                    $table->dropForeign($constraint);
                });

                Schema::table($childTable, function (Blueprint $table) use ($foreignKey, $parentTable): void {
                    $table->foreign($foreignKey)
                        ->references('id')
                        ->on($parentTable)
                        ->cascadeOnDelete();
                });
            }
        }

        foreach ($this->pivotTables as $tableName => [$leftKey, $leftTable, $rightKey, $rightTable]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            $leftConstraint = 'ws_'.substr(hash('sha256', $tableName.'_left'), 0, 10).'_fk';
            $rightConstraint = 'ws_'.substr(hash('sha256', $tableName.'_right'), 0, 10).'_fk';

            Schema::table($tableName, function (Blueprint $table) use ($leftConstraint, $rightConstraint): void {
                $table->dropForeign($leftConstraint);
                $table->dropForeign($rightConstraint);
            });

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $leftKey, $leftTable, $rightKey, $rightTable): void {
                $table->foreign($leftKey)->references('id')->on($leftTable)->cascadeOnDelete();

                if ($tableName === 'publication_product') {
                    $table->foreign($rightKey)->references('id')->on($rightTable)->restrictOnDelete();
                } else {
                    $table->foreign($rightKey)->references('id')->on($rightTable)->cascadeOnDelete();
                }
            });
        }

        $workspaceUniqueIndexes = [
            'categories' => ['categories_workspace_nombre_unique'],
            'products' => ['products_workspace_sku_unique'],
            'facebook_groups' => ['facebook_groups_workspace_facebook_id_unique'],
            'whatsapp_groups' => ['whatsapp_groups_workspace_whatsapp_id_unique'],
            'publication_destinations' => ['publication_destinations_workspace_platform_external_id_unique'],
            'external_catalog_items' => ['external_catalog_items_workspace_source_external_id_unique'],
            'external_catalog_warehouses' => ['external_catalog_warehouses_workspace_source_external_id_unique'],
            'integration_event_inbox' => ['integration_event_inbox_workspace_event_id_unique'],
            'bot_statuses' => ['bot_statuses_workspace_name_unique'],
            'bot_commands' => ['bot_commands_workspace_idempotency_unique'],
            'publication_executions' => ['publication_executions_workspace_occurrence_target_unique'],
        ];

        foreach ($workspaceUniqueIndexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $indexName) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropUnique($indexName);
                });
            }
        }

        foreach ($this->tenantTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'workspace_id')) {
                $indexName = 'ws_'.substr(hash('sha256', $tableName), 0, 10).'_id_uq';

                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropUnique($indexName);
                });
            }
        }

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

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign('products_categoria_id_foreign');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('categoria_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });

        // Global unique indexes are not restored automatically because different
        // workspaces may now contain the same business identifiers.
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

    private function enforceWorkspaceOwnership(): void
    {
        foreach ($this->tenantTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
            });

            $indexName = 'ws_'.substr(hash('sha256', $tableName), 0, 10).'_id_uq';

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->unique(['workspace_id', 'id'], $indexName);
            });
        }

        $this->dropForeignKeysForColumns('products', ['categoria_id']);

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('categoria_id', 'products_categoria_id_foreign')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });

        $dependentRelations = [
            'inventory_movements' => [['producto_id', 'products']],
            'publication_executions' => [['publication_id', 'publications']],
            'publication_schedules' => [['publication_id', 'publications']],
            'external_catalog_campaign_selections' => [
                ['campaign_id', 'external_catalog_campaigns'],
                ['external_catalog_item_id', 'external_catalog_items'],
            ],
            'external_catalog_campaign_triggers' => [
                ['campaign_id', 'external_catalog_campaigns'],
                ['external_catalog_item_id', 'external_catalog_items'],
            ],
        ];

        foreach ($dependentRelations as $childTable => $relations) {
            if (! Schema::hasTable($childTable) || ! Schema::hasColumn($childTable, 'workspace_id')) {
                continue;
            }

            foreach ($relations as [$foreignKey, $parentTable]) {
                if (! Schema::hasColumn($childTable, $foreignKey) || ! Schema::hasTable($parentTable)) {
                    continue;
                }

                $this->dropForeignKeysForColumns($childTable, [$foreignKey]);

                $constraint = 'ws_'.substr(hash('sha256', $childTable.'_'.$foreignKey), 0, 10).'_fk';

                Schema::table($childTable, function (Blueprint $table) use ($foreignKey, $parentTable, $constraint): void {
                    $table->foreign(['workspace_id', $foreignKey], $constraint)
                        ->references(['workspace_id', 'id'])
                        ->on($parentTable)
                        ->cascadeOnDelete();
                });
            }
        }

        foreach ($this->pivotTables as $tableName => [$leftKey, $leftTable, $rightKey, $rightTable]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            $this->dropForeignKeysForColumns($tableName, [$leftKey, $rightKey]);

            Schema::table($tableName, function (Blueprint $table) use (
                $tableName,
                $leftKey,
                $leftTable,
                $rightKey,
                $rightTable,
            ): void {
                $table->unsignedBigInteger('workspace_id')->nullable(false)->change();

                $leftConstraint = 'ws_'.substr(hash('sha256', $tableName.'_left'), 0, 10).'_fk';
                $rightConstraint = 'ws_'.substr(hash('sha256', $tableName.'_right'), 0, 10).'_fk';

                $table->foreign(['workspace_id', $leftKey], $leftConstraint)
                    ->references(['workspace_id', 'id'])
                    ->on($leftTable)
                    ->cascadeOnDelete();

                $rightForeignKey = $table->foreign(['workspace_id', $rightKey], $rightConstraint)
                    ->references(['workspace_id', 'id'])
                    ->on($rightTable);

                if ($tableName === 'publication_product') {
                    $rightForeignKey->restrictOnDelete();
                } else {
                    $rightForeignKey->cascadeOnDelete();
                }
            });
        }
    }

    private function dropForeignKeysForColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (array_intersect($columns, $foreignKey['columns'] ?? [])) {
                Schema::table($tableName, function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey['name']);
                });
            }
        }
    }

    private function replaceGlobalUniqueIndexes(): void
    {
        $indexes = [
            'categories' => [
                ['existing' => ['nombre'], 'columns' => ['workspace_id', 'nombre'], 'name' => 'categories_workspace_nombre_unique'],
            ],
            'products' => [
                ['existing' => ['sku'], 'columns' => ['workspace_id', 'sku'], 'name' => 'products_workspace_sku_unique'],
            ],
            'facebook_groups' => [
                ['existing' => ['facebook_id'], 'columns' => ['workspace_id', 'facebook_id'], 'name' => 'facebook_groups_workspace_facebook_id_unique'],
            ],
            'whatsapp_groups' => [
                ['existing' => ['whatsapp_id'], 'columns' => ['workspace_id', 'whatsapp_id'], 'name' => 'whatsapp_groups_workspace_whatsapp_id_unique'],
            ],
            'publication_destinations' => [
                ['existing' => ['platform', 'external_id'], 'columns' => ['workspace_id', 'platform', 'external_id'], 'name' => 'publication_destinations_workspace_platform_external_id_unique'],
            ],
            'external_catalog_items' => [
                ['existing' => ['source', 'external_id'], 'columns' => ['workspace_id', 'source', 'external_id'], 'name' => 'external_catalog_items_workspace_source_external_id_unique'],
            ],
            'external_catalog_warehouses' => [
                ['existing' => ['source', 'external_id'], 'columns' => ['workspace_id', 'source', 'external_id'], 'name' => 'external_catalog_warehouses_workspace_source_external_id_unique'],
            ],
            'integration_event_inbox' => [
                ['existing' => ['event_id'], 'columns' => ['workspace_id', 'event_id'], 'name' => 'integration_event_inbox_workspace_event_id_unique'],
            ],
            'bot_statuses' => [
                ['existing' => ['name'], 'columns' => ['workspace_id', 'name'], 'name' => 'bot_statuses_workspace_name_unique'],
            ],
            'bot_commands' => [
                ['existing' => ['idempotency_key'], 'columns' => ['workspace_id', 'idempotency_key'], 'name' => 'bot_commands_workspace_idempotency_unique'],
            ],
            'publication_executions' => [
                ['existing' => ['occurrence_id', 'target_key'], 'columns' => ['workspace_id', 'occurrence_id', 'target_key'], 'name' => 'publication_executions_workspace_occurrence_target_unique'],
            ],
        ];

        foreach ($indexes as $tableName => $definitions) {
            foreach ($definitions as $definition) {
                $existing = collect(Schema::getIndexes($tableName))->first(
                    fn (array $index): bool => ($index['unique'] ?? false)
                        && $index['columns'] === $definition['existing'],
                );

                if ($existing) {
                    Schema::table($tableName, function (Blueprint $table) use ($existing): void {
                        $table->dropUnique($existing['name']);
                    });
                }

                Schema::table($tableName, function (Blueprint $table) use ($definition): void {
                    $table->unique($definition['columns'], $definition['name']);
                });
            }
        }
    }
};
