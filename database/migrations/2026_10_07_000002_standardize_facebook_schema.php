<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->standardizeFacebookGroups();
        $this->standardizeFacebookGroupPublication();
    }

    public function down(): void
    {
        // This migration is intentionally irreversible because it standardizes
        // persisted schema names without removing application data.
    }

    private function standardizeFacebookGroups(): void
    {
        if (! Schema::hasTable('facebook_groups')) {
            return;
        }

        Schema::table('facebook_groups', function (Blueprint $table): void {
            if (
                Schema::hasColumn('facebook_groups', 'nombre') &&
                ! Schema::hasColumn('facebook_groups', 'name')
            ) {
                $table->renameColumn('nombre', 'name');
            }

            if (
                Schema::hasColumn('facebook_groups', 'activo') &&
                ! Schema::hasColumn('facebook_groups', 'active')
            ) {
                $table->renameColumn('activo', 'active');
            }

            if (
                Schema::hasColumn('facebook_groups', 'ultima_sincronizacion') &&
                ! Schema::hasColumn('facebook_groups', 'last_synchronized_at')
            ) {
                $table->renameColumn(
                    'ultima_sincronizacion',
                    'last_synchronized_at'
                );
            }
        });
    }

    private function standardizeFacebookGroupPublication(): void
    {
        $legacyTable = 'facebook_grupo_publicacion';
        $targetTable = 'facebook_group_publication';

        if (
            Schema::hasTable($legacyTable) &&
            ! Schema::hasTable($targetTable)
        ) {
            Schema::rename($legacyTable, $targetTable);
        }

        if (! Schema::hasTable($targetTable)) {
            Schema::create($targetTable, function (Blueprint $table): void {
                $table->id();

                $table->foreignId('publication_id')
                    ->constrained('publications')
                    ->cascadeOnDelete();

                $table->foreignId('facebook_group_id')
                    ->constrained('facebook_groups')
                    ->cascadeOnDelete();

                $table->timestamps();

                $table->unique([
                    'publication_id',
                    'facebook_group_id',
                ]);
            });

            return;
        }

        $hasLegacyColumns =
            Schema::hasColumn($targetTable, 'publicacion_id') ||
            Schema::hasColumn($targetTable, 'facebook_grupo_id');

        $hasEnglishColumns =
            Schema::hasColumn($targetTable, 'publication_id') &&
            Schema::hasColumn($targetTable, 'facebook_group_id');

        if (! $hasLegacyColumns || $hasEnglishColumns) {
            return;
        }

        $legacyCopy = 'facebook_group_publication_legacy';

        if (Schema::hasTable($legacyCopy)) {
            Schema::drop($legacyCopy);
        }

        Schema::rename($targetTable, $legacyCopy);

        Schema::create($targetTable, function (Blueprint $table): void {
            $table->id();

            $table->foreignId('publication_id')
                ->constrained('publications')
                ->cascadeOnDelete();

            $table->foreignId('facebook_group_id')
                ->constrained('facebook_groups')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'publication_id',
                'facebook_group_id',
            ]);
        });

        $columns = Schema::getColumnListing($legacyCopy);
        $hasCreatedAt = in_array('created_at', $columns, true);
        $hasUpdatedAt = in_array('updated_at', $columns, true);

        $selectColumns = [
            'publicacion_id',
            'facebook_grupo_id',
        ];

        if ($hasCreatedAt) {
            $selectColumns[] = 'created_at';
        }

        if ($hasUpdatedAt) {
            $selectColumns[] = 'updated_at';
        }

        $rows = DB::table($legacyCopy)
            ->select($selectColumns)
            ->get();

        foreach ($rows as $row) {
            $data = [
                'publication_id' => $row->publicacion_id,
                'facebook_group_id' => $row->facebook_grupo_id,
            ];

            if ($hasCreatedAt) {
                $data['created_at'] = $row->created_at;
            }

            if ($hasUpdatedAt) {
                $data['updated_at'] = $row->updated_at;
            }

            DB::table($targetTable)->insertOrIgnore($data);
        }

        Schema::drop($legacyCopy);
    }
};
