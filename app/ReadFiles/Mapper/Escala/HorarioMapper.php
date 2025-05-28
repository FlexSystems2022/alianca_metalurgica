<?php
namespace App\ReadFiles\Mapper\Escala;

class HorarioMapper
{
    public static function handle(array $data): array
    {
        return [
            'descricao' => $data[0] ?? null,
            'sequencia' => $data[1] ?? null,
            'hora_marcacao' => $data[2] ?? null
        ];
    }
}