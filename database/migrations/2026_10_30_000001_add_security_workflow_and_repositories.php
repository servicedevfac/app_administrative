<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('api_token_hash', 64)->nullable()->unique()->after('remember_token');
            $table->timestamp('last_login_at')->nullable()->after('api_token_hash');
        });

        Schema::table('material_requests', function (Blueprint $table) {
            $table->string('status', 50)->default('Soumise')->change();
            $table->timestamp('submitted_at')->nullable()->after('approved_at');
            $table->timestamp('closed_at')->nullable()->after('delivered_at');
            $table->text('correction_reason')->nullable()->after('rejection_reason');
        });

        Schema::table('administrative_documents', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('file_path');
            $table->string('mime_type')->nullable()->after('original_name');
            $table->unsignedBigInteger('file_size_bytes')->nullable()->after('mime_type');
        });

        Schema::create('request_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->constrained('material_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('status_from', 50)->nullable();
            $table->string('status_to', 50);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->nullableMorphs('subject');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('article_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('measurement_units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('symbol', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('article_categories')->insertOrIgnore([
            ['name' => 'Papeterie', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Informatique', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fournitures', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gadgets', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('measurement_units')->insertOrIgnore([
            ['name' => 'Pièce', 'symbol' => 'pc', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Rame', 'symbol' => 'rame', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Boîte', 'symbol' => 'boîte', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Unité', 'symbol' => 'u', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_units');
        Schema::dropIfExists('article_categories');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('request_decisions');

        Schema::table('administrative_documents', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'mime_type', 'file_size_bytes']);
        });

        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'closed_at', 'correction_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn(['api_token_hash', 'last_login_at']);
        });
    }
};
