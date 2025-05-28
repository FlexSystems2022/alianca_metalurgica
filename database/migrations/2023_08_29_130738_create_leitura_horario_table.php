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
        Schema::create('leitura_horario', function (Blueprint $table) {
            $table->id();

            $table->string('descricao');
            $table->integer('sequencia');
            $table->time('hora_marcacao');

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
        Schema::dropIfExists('leitura_horario');
    }
};
