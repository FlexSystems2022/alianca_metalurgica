<?php

namespace App\Services\ViaCep\Exceptions;

class CepException extends \InvalidArgumentException
{
    /**
     * Create invalid Cep the exception.
     **/
    public static function invalidCep(string $cep)
    {
        return new static("CEP: {$cep} - is invalid", 404);
    }
}
