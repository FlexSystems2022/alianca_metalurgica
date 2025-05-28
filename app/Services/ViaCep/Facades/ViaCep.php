<?php

namespace App\Services\ViaCep\Facades;

use App\Services\ViaCep\ViaCepService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Services\ViaCep\Endpoints\Cep cep()
 */
class ViaCep extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor()
    {
        return ViaCepService::class;
    }
}