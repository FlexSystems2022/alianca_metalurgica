<?php

namespace App\Services\ViaCep\Endpoints;

use App\Services\ViaCep\ViaCepService;

abstract class BaseEndpoint
{
    /**
     * @var ViaCepService $service
     */
    protected $service;

    /**
     * Create instance
     */
    public function __construct(?ViaCepService $service=null)
    {
        $this->service = $service ?? new ViaCepService;
    }
}
