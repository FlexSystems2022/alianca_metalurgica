<?php

namespace App\Services\ViaCep\Endpoints;

use GuzzleHttp\Psr7\Request;
use App\Services\ViaCep\Entities\Address;
use App\Services\ViaCep\Rule\ValidateCep;
use App\Services\ViaCep\Endpoints\BaseEndpoint;
use App\Services\ViaCep\Exceptions\CepException;
use App\Services\ViaCep\Exceptions\RateLimitException;

class Cep extends BaseEndpoint
{
    /**
     * Get Cep
     */
    public function get(string $cep): Address
    {
        $validatedCep = ValidateCep::validate($cep);

        $cached = $this->service->getDataFromRepository($validatedCep);
        if($cached) {
            return $this->transform($cached, $validatedCep);
        }

        try {
            $json = retry(2, function() use($validatedCep): ?array {
                $request = new Request('GET', "ws/{$validatedCep}/json/");

                $response = $this->service->api->sendAsync($request)->wait();

                if($response->getStatusCode() === 429) {
                    throw RateLimitException::make();
                }

                if($response->getStatusCode() === 404) {
                    return [];
                }

                if($response->getStatusCode() !== 200) {
                    return [];
                }

                return json_decode($response->getBody()->getContents(), JSON_OBJECT_AS_ARRAY);
            }, 5 * 600);
        } catch(RateLimitException $rateLimitException) {
            return $this->get($validatedCep);
        }

        $this->service->setDataFromRepository($validatedCep, $json);

        return $this->transform($json, $validatedCep);
    }

    /**
     * Transform Response Cep
     */
    public function transform(?array $json, string $cep): Address
    {
        if(!$json || data_get($json, 'erro')) {
            throw CepException::invalidCep($cep);
        }

        return new Address($json);
    }
}
