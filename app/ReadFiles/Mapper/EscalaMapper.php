<?php

namespace App\ReadFiles\Mapper;

use Carbon\Carbon;

class EscalaMapper
{
    public static function handle(array $data): array
    {
        return [
            'descricao' => $data[0] ?? null,
            'turma' => $data[1] ?? null,
            'data' => self::formatDate($data[2] ?? null)
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