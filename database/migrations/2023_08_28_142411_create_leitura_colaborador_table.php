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
        Schema::create('leitura_colaborador', function (Blueprint $table) {
            $table->id();
            
            $table->string('empresa');
            $table->integer('cnpj_empresa');
            $table->integer('matricula');
            $table->string('nome');
            $table->date('data_admissao');
            $table->date('data_demissao')->nullable();
            $table->date('data_nascimento');
            $table->string('numero_pis');
            $table->string('cpf', 15);
            $table->char('genero', 1)->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->string('nome_pai')->nullable();
            $table->string('nome_mae')->nullable();
            $table->string('codigo_cargo')->nullable();
            $table->string('cargo')->nullable();
            $table->string('codigo_posto')->nullable();
            $table->string('posto')->nullable();
            $table->string('codigo_cr')->nullable();
            $table->string('cliente_cr')->nullable();
            $table->string('cnpj_cliente')->nullable();
            $table->string('codigo_sindicato')->nullable();
            $table->string('descricao_sindicato')->nullable();
            $table->string('cod_categoria_profissional')->nullable();
            $table->string('desc_categoria_profissional')->nullable();
            
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
        Schema::dropIfExists('leitura_colaborador');
    }
};
