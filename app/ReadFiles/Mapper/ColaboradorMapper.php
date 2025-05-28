<?php

namespace App\ReadFiles\Mapper;

use Carbon\Carbon;

class ColaboradorMapper
{
    public static function handle(array $data): array
    {
        return [
            'empresa' => $data[0] ?? null,
            'cnpj_empresa' => $data[1] ?? null,
            'matricula' => $data[2] ?? null,
            'nome' => $data[3] ?? null,
            'data_admissao' => self::formatDate($data[4] ?? null),
            'data_demissao' => self::formatDate($data[5] ?? null),
            'data_nascimento' => self::formatDate($data[6] ?? null),
            'numero_pis' => $data[7] ?? null,
            'cpf' => $data[8],
            'genero' => str_replace("'", '', $data[9] ?? null) ?: null,
            'telefone' => $data[10] ?? null,
            'email' => $data[11] ?? null,
            'nome_pai' => $data[12] ?? null,
            'nome_mae' => $data[13] ?? null,
            'codigo_cargo' => $data[14] ?? null,
            'cargo' => $data[15] ?? null,
            'codigo_posto' => $data[16] ?? null,
            'posto' => $data[17] ?? null,
            'codigo_cr' => $data[18] ?? null,
            'cliente_cr' => $data[19] ?? null,
            'cnpj_cliente' => $data[20] ?? null,
            'codigo_sindicato' => $data[21] ?? null,
            'descricao_sindicato' => $data[22] ?? null,
            'cod_categoria_profissional' => $data[23] ?? null,
            'desc_categoria_profissional' => $data[24] ?? null
        ];
    }

    private static function formatDate(?string $date=null): string|null
    {
        if(!$date) {
            return null;
        }

        return Carbon::parse($date)->format('Y-m-d');
    }
}