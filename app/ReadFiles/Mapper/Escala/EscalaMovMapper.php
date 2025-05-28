<?php

namespace App\ReadFiles\Mapper\Escala;

use Carbon\Carbon;

class EscalaMovMapper
{
    public static function handle(array $data): array
    {
        return [
            'empresa' => $data[0] ?? null,
            'cgc_empresa' => $data[1] ?? null,
            'matricula' => $data[2] ?? null,
            'tipo' => $data[3] ?? null,
            'data' => self::formatDate($data[4] ?? null),
            'codigo' => $data[5] ?? null,
            'codigo_turma' => $data[6] ?? null
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