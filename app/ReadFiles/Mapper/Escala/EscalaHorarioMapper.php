<?php

namespace App\ReadFiles\Mapper\Escala;

class EscalaHorarioMapper
{
    public static function handle(array $data): array
    {
        return [
            'desc_escala' => $data[0] ?? null,
            'desc_horario' => $data[1] ?? null
        ];
    }
}