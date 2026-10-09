<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->unsignedInteger('interval_minutes')
                ->nullable()
                ->after('interval_hours');
        });

        DB::table('publications')
            ->where('recurrence_type', 'hourly')
            ->whereNotNull('interval_hours')
            ->update([
                'interval_minutes' => DB::raw('interval_hours * 60'),
            ]);
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->dropColumn('interval_minutes');
        });
    }
};
