<?php

namespace App\Services\ViaCep\Endpoints;

use App\Services\ViaCep\Endpoints\Cep;
use App\Services\ViaCep\ViaCepService;

trait HasCep
{
    /**
     * Create instance Cep Endpoint
     *
     * @return \App\Services\ViaCep\Endpoints\Cep
     */
    public function cep(): Cep
    {
        if($this instanceof ViaCepService) {
            return new Cep($this);
        }

        return new Cep();
    }
}
