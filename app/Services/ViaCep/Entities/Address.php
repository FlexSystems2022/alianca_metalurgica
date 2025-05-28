<?php

namespace App\Services\ViaCep\Entities;

class Address
{
    /**
     * Cep address
     */
    public $cep;

    /**
     * Logradouro address
     */
    public $logradouro;

    /**
     * Complemento address
     */
    public $complemento = null;

    /**
     * Unidade address
     */
    public $unidade = null;

    /**
     * Bairro address
     */
    public $bairro = null;

    /**
     * Localidade address
     */
    public $localidade = null;

    /**
     * UF address
     */
    public $uf = null;

    /**
     * Ibge address
     */
    public $ibge = null;

    /**
     * Gia address
     */
    public $gia = null;

    /**
     * DDD address
     */
    public $ddd = null;

    /**
     * Siafi address
     */
    public $siafi = null;

    /**
     * Create new instance from api
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->cep = data_get($data, 'cep');
        $this->logradouro = data_get($data, 'logradouro');
        $this->complemento = data_get($data, 'complemento') ?: null;
        $this->unidade = data_get($data, 'unidade') ?: null;
        $this->bairro = data_get($data, 'bairro') ?: null;
        $this->localidade = data_get($data, 'localidade') ?: null;
        $this->uf = data_get($data, 'uf') ?: null;
        $this->ibge = data_get($data, 'ibge') ?: null;
        $this->gia = data_get($data, 'gia') ?: null;
        $this->ddd = data_get($data, 'ddd') ?: null;
        $this->siafi = data_get($data, 'siafi') ?: null;
    }
}
