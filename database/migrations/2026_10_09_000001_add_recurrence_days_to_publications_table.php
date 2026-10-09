<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Stores ISO weekday numbers (1 = Monday ... 7 = Sunday) for weekly schedules.
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->json('recurrence_days')
                ->nullable()
                ->after('recurrence_type');
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->dropColumn('recurrence_days');
        });
    }
};
