<?php

namespace App\Services\ViaCep\Exceptions;

class RateLimitException extends \RuntimeException
{
    /**
     * Create invalid Cep the exception.
     **/
    public static function make()
    {
        return new static('Too many Requests', 429);
    }
}
