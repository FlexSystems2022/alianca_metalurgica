<?php

namespace App\Services\ViaCep;

use GuzzleHttp\Client;
use App\Services\ViaCep\Endpoints\HasCep;
use App\Services\ViaCep\Repository\RepositoryContract;
use App\Services\ViaCep\Repository\RepositoryManager;

class ViaCepService
{
    use HasCep;

    /**
     * Api Requester
     *
     * @var Client $api
     */
    public $api;

    /**
     * Api Requester
     *
     * @var RepositoryContract $repository
     */
    public $repository;

    /**
     * Create new instance of service
     */
    public function __construct()
    {
        $this->api = new Client([
            'timeout' => 2,
            'http_errors' => false,
            'base_uri' => 'https://viacep.com.br/',
            'headers' => [
                'User-Agent' => 'Resolv',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]
        ]);

        $this->repository = RepositoryManager::factory('database');
    }

    /**
     * Get data to repository
     */
    public function getDataFromRepository(string $key)
    {
        return $this->repository->get($key);
    }

    /**
     * Set data to repository
     */
    public function setDataFromRepository(string $key, $data): self
    {
        $this->repository->put($key, $data);

        return $this;
    }
}
