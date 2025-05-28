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
        Schema::create('leitura_escala_horario', function (Blueprint $table) {
            $table->id();

            $table->string('desc_escala');
            $table->string('desc_horario');

            $table->string('documento_create')->nullable();
            $table->string('documento_last')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leitura_escala_horario');
    }
};
