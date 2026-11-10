<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('workspace_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('collaborator');
            $table->json('permissions')->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 32)->default('editor');
            $table->json('permissions')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'email']);
        });

        Schema::create('workspace_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 120);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['workspace_id', 'event', 'created_at']);
        });

        // Preserve the existing single-workspace installation and all current users.
        // Existing data is not moved or deleted by this migration.
        $firstUser = DB::table('users')->orderBy('id')->first();

        if ($firstUser) {
            $workspaceId = DB::table('workspaces')->insertGetId([
                'name' => 'Espacio de trabajo principal',
                'slug' => 'principal-' . Str::lower(Str::random(8)),
                'created_by_user_id' => $firstUser->id,
                'settings' => json_encode(['legacy_migration' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (DB::table('users')->orderBy('id')->get() as $index => $user) {
                DB::table('workspace_memberships')->insert([
                    'workspace_id' => $workspaceId,
                    'user_id' => $user->id,
                    'role' => $index === 0 ? 'owner' : 'admin',
                    'permissions' => null,
                    'status' => 'active',
                    'invited_by_user_id' => $index === 0 ? null : $firstUser->id,
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_audit_logs');
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_memberships');
        Schema::dropIfExists('workspaces');
    }
};
