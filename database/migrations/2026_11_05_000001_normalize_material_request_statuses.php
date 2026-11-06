<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('material_requests')
            ->whereIn('status', ['Rejetée', 'Annulée'])
            ->update(['status' => 'Rejetée']);

        DB::table('material_requests')
            ->whereIn('status', [
                'Validée',
                'Approuvée',
                "En cours d'exécution",
                'En cours d’exécution',
                "En attente d'approvisionnement",
                'En attente d’approvisionnement',
                'Exécutée',
                'En attente de justification',
                'Livrée',
                'Restituée',
                'Clôturée',
            ])
            ->update(['status' => 'Validée']);

        DB::table('material_requests')
            ->where('status', 'En attente de vérification')
            ->update(['status' => 'En attente de validation']);

        DB::table('material_requests')
            ->whereNotIn('status', ['Soumise', 'En attente de validation', 'Rejetée', 'Validée'])
            ->update(['status' => 'Soumise']);

        Schema::table('material_requests', function (Blueprint $table): void {
            $table->string('status', 50)->default('Soumise')->change();
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table): void {
            $table->string('status', 50)->default('En attente')->change();
        });
    }
};
