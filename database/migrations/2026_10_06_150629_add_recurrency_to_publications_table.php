<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {

            $table->string('tipo_recurrencia')
                ->default('ninguna')
                ->after('hora');

            $table->unsignedTinyInteger('intervalo_horas')
                ->nullable()
                ->after('tipo_recurrencia');

            $table->time('hora_inicio_recurrencia')
                ->nullable()
                ->after('intervalo_horas');

            $table->time('hora_fin_recurrencia')
                ->nullable()
                ->after('hora_inicio_recurrencia');

            $table->date('fecha_fin')
                ->nullable()
                ->after('hora_fin_recurrencia');

        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {

            $table->dropColumn([
                'tipo_recurrencia',
                'intervalo_horas',
                'hora_inicio_recurrencia',
                'hora_fin_recurrencia',
                'fecha_fin',
            ]);

        });
    }
};