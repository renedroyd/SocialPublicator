<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('publication_product')) {
            return;
        }

        $renames = [
            'publicacion_id' => 'publication_id',
            'producto_id' => 'product_id',
            'cantidad' => 'quantity',
            'precio_publicado' => 'published_price',
            'orden' => 'sort_order',
        ];

        Schema::table('publication_product', function (Blueprint $table) use ($renames): void {
            foreach ($renames as $old => $new) {
                if (
                    Schema::hasColumn('publication_product', $old) &&
                    !Schema::hasColumn('publication_product', $new)
                ) {
                    $table->renameColumn($old, $new);
                }
            }
        });
    }

    public function down(): void
    {
        // The schema standardization is intentionally not reversed automatically.
    }
};
