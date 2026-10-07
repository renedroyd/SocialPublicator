<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'fecha' => 'date',
            'hora' => 'time',
            'imagen' => 'image',
            'texto' => 'text',
            'activo' => 'active',
            'destino' => 'destination',
            'tipo_recurrencia' => 'recurrence_type',
            'intervalo_horas' => 'interval_hours',
            'hora_inicio_recurrencia' => 'recurrence_start_time',
            'hora_fin_recurrencia' => 'recurrence_end_time',
            'fecha_fin' => 'end_date',
        ];

        Schema::table('publications', function (Blueprint $table) use ($renames): void {
            foreach ($renames as $old => $new) {
                if (
                    Schema::hasColumn('publications', $old) &&
                    !Schema::hasColumn('publications', $new)
                ) {
                    $table->renameColumn($old, $new);
                }
            }
        });

        DB::table('publications')
            ->where('recurrence_type', 'ninguna')
            ->update(['recurrence_type' => 'none']);

        DB::table('publications')
            ->where('recurrence_type', 'cada_horas')
            ->update(['recurrence_type' => 'hourly']);

        DB::table('publications')
            ->where('destination', 'ambos')
            ->update(['destination' => 'both']);
    }

    public function down(): void
    {
        // The schema standardization is intentionally not reversed automatically.
    }
};
