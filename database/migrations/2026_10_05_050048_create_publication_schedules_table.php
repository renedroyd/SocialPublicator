<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_schedules', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('publication_id')
                ->unique()
                ->constrained('publications')
                ->cascadeOnDelete();

            /*
             * once
             * daily
             * weekly
             * monthly
             */
            $table->string('frequency', 20)
                ->default('once')
                ->index();

            $table->unsignedInteger('interval')
                ->default(1);

            /*
             * Para recurrencias semanales:
             * [1, 5] = lunes y viernes
             */
            $table->json('weekdays')->nullable();

            /*
             * Para recurrencias mensuales.
             */
            $table->unsignedTinyInteger('month_day')->nullable();

            /*
             * never
             * date
             * occurrences
             */
            $table->string('end_type', 20)
                ->default('never');

            $table->date('end_date')->nullable();

            $table->unsignedInteger('max_occurrences')->nullable();

            /*
             * Control de ejecución.
             */
            $table->unsignedInteger('occurrences_count')
                ->default(0);

            $table->timestamp('next_run_at')->nullable()->index();

            $table->timestamp('last_run_at')->nullable();

            $table->boolean('active')
                ->default(true)
                ->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_schedules');
    }
};
