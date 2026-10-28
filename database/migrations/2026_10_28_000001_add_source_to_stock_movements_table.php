<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('material_request_id')
                ->nullable()
                ->after('user_id')
                ->constrained('material_requests')
                ->nullOnDelete();
            $table->string('source')->default('manual_adjustment')->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_request_id');
            $table->dropColumn('source');
        });
    }
};
