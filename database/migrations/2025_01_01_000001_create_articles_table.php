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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // ex: ART-001
            $table->string('name'); // ex: Ramette papier A4
            $table->string('category')->default('Fournitures'); // Papeterie, Informatique, Fournitures, Gadgets, Autres
            $table->string('unit')->default('Pièce'); // Pièce, Rame, Boîte
            $table->integer('quantity')->default(0);
            $table->integer('threshold')->default(5);
            $table->string('location')->nullable(); // Rayon B3, Magasin A
            $table->enum('status', ['En stock', 'Stock faible', 'Rupture'])->default('En stock');
            $table->boolean('is_available')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
