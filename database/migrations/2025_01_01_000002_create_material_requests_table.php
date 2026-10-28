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
        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // ex: DEM-2025-00045
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type_operation', ['Emprunt temporaire', 'Dotation permanente'])->default('Emprunt temporaire');
            $table->string('motif');
            $table->string('service')->default('Ressources Humaines');
            $table->date('date_needed')->nullable();
            $table->date('date_return')->nullable(); // restitution si emprunt
            $table->enum('urgency', ['Normale', 'Urgente', 'Planifiée'])->default('Normale');
            $table->enum('status', ['En attente', 'Approuvée', 'Rejetée', 'Restituée'])->default('En attente');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_requests');
    }
};
