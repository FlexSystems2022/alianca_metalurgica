<?php

namespace App\ReadFiles\Mapper;

use Carbon\Carbon;

class TrocaAfastamentoMapper
{
    public static function handle(array $data): array
    {
        return [
            'empresa' => $data[0] ?? null,
            'cgc_empresa' => $data[1] ?? null,
            'matricula' => $data[2] ?? null,
            'tipo' => $data[3] ?? null,
            'data' => self::formatDate($data[4] ?? null),
            'ausencia' => $data[5] ?? null
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