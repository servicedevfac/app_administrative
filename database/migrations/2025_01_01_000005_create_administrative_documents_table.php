<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('administrative_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->nullable()->constrained('material_requests')->nullOnDelete();
            $table->string('title');
            $table->string('category')->default('Documents internes');
            $table->enum('type', ['PDF', 'Word', 'Excel'])->default('PDF');
            $table->string('service')->default('Direction');
            $table->string('file_path')->nullable();
            $table->string('file_size')->nullable()->default('140 Ko');
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administrative_documents');
    }
};
