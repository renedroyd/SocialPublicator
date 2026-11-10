<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_memberships', function (Blueprint $table): void {
            $table->unique(['workspace_id', 'id'], 'workspace_memberships_workspace_id_id_unique');
        });

        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_account_id')->nullable();
            $table->string('platform', 24);
            $table->string('account_type', 40);
            $table->string('external_account_id', 191)->nullable();
            $table->string('display_name');
            $table->string('username')->nullable();
            $table->text('avatar_url')->nullable();
            $table->string('status', 24)->default('pending');
            $table->json('capabilities')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'id'], 'social_accounts_workspace_id_id_unique');
            $table->unique(
                ['workspace_id', 'platform', 'account_type', 'external_account_id'],
                'social_accounts_external_identity_unique',
            );
            $table->index(['workspace_id', 'platform', 'status'], 'social_accounts_workspace_platform_status_index');
        });

        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->foreign(['workspace_id', 'parent_account_id'], 'social_accounts_workspace_parent_fk')
                ->references(['workspace_id', 'id'])
                ->on('social_accounts')
                ->restrictOnDelete();
        });

        Schema::create('social_account_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('social_account_id');
            $table->string('connection_type', 32);
            $table->string('provider', 32);
            $table->string('status', 24)->default('pending');
            $table->string('agent_instance_id', 120)->nullable();
            $table->string('session_reference', 191)->nullable();
            $table->string('provider_connection_id', 191)->nullable();
            $table->longText('credentials')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->foreign(['workspace_id', 'social_account_id'], 'social_account_connections_workspace_account_fk')
                ->references(['workspace_id', 'id'])
                ->on('social_accounts')
                ->cascadeOnDelete();
            $table->index(['workspace_id', 'status'], 'social_account_connections_workspace_status_index');
            $table->index(['workspace_id', 'social_account_id'], 'social_account_connections_workspace_account_index');
        });

        Schema::create('workspace_membership_social_account_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('social_account_id');
            $table->json('permissions');
            $table->timestamps();

            $table->unique(
                ['workspace_id', 'membership_id', 'social_account_id'],
                'membership_social_account_permissions_unique',
            );
            $table->foreign(['workspace_id', 'membership_id'], 'membership_social_account_permissions_membership_fk')
                ->references(['workspace_id', 'id'])
                ->on('workspace_memberships')
                ->cascadeOnDelete();
            $table->foreign(['workspace_id', 'social_account_id'], 'membership_social_account_permissions_account_fk')
                ->references(['workspace_id', 'id'])
                ->on('social_accounts')
                ->cascadeOnDelete();
        });

        Schema::create('workspace_membership_destination_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('membership_id');
            $table->string('destination_type', 48);
            $table->unsignedBigInteger('destination_id');
            $table->json('permissions');
            $table->timestamps();

            $table->unique(
                ['workspace_id', 'membership_id', 'destination_type', 'destination_id'],
                'membership_destination_permissions_unique',
            );
            $table->foreign(['workspace_id', 'membership_id'], 'membership_destination_permissions_membership_fk')
                ->references(['workspace_id', 'id'])
                ->on('workspace_memberships')
                ->cascadeOnDelete();
            $table->index(
                ['workspace_id', 'destination_type', 'destination_id'],
                'membership_destination_permissions_target_index',
            );
        });

        foreach (['facebook_groups', 'whatsapp_groups', 'publication_destinations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->unsignedBigInteger('social_account_id')->nullable();
                $table->index(
                    ['workspace_id', 'social_account_id'],
                    $tableName.'_workspace_social_account_index',
                );

                $constraint = 'sa_'.substr(hash('sha256', $tableName), 0, 10).'_fk';

                $table->foreign(['workspace_id', 'social_account_id'], $constraint)
                    ->references(['workspace_id', 'id'])
                    ->on('social_accounts')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            throw new RuntimeException('Social account migration rollback on SQLite requires a manual table rebuild.');
        }

        foreach (['facebook_groups', 'whatsapp_groups', 'publication_destinations'] as $tableName) {
            $constraint = 'sa_'.substr(hash('sha256', $tableName), 0, 10).'_fk';

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $constraint): void {
                $table->dropForeign($constraint);
                $table->dropIndex($tableName.'_workspace_social_account_index');
                $table->dropColumn('social_account_id');
            });
        }

        Schema::dropIfExists('workspace_membership_destination_permissions');
        Schema::dropIfExists('workspace_membership_social_account_permissions');
        Schema::dropIfExists('social_account_connections');
        Schema::dropIfExists('social_accounts');

        Schema::table('workspace_memberships', function (Blueprint $table): void {
            $table->dropUnique('workspace_memberships_workspace_id_id_unique');
        });
    }
};
