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
        Schema::create('leitura_mov_afastamento', function (Blueprint $table) {
            $table->id();
                        
            $table->string('empresa');
            $table->integer('cgc_empresa');
            $table->integer('matricula');
            $table->string('tipo');
            $table->date('data');
            $table->string('ausencia');
            
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
        Schema::dropIfExists('leitura_mov_afastamento');
    }
};
