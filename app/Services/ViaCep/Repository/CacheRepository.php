<?php

namespace App\Services\ViaCep\Repository;

use Illuminate\Support\Facades\Cache;

class CacheRepository implements RepositoryContract
{
	/**
	 * Get data
	 * 
	 * @return mixed
	 */
	public function get(string $key)
	{
		return Cache::get("via-cep-{$key}");
	}

	/**
	 * Put data
	 */
	public function put(string $key, $data): void
	{
        Cache::forever("via-cep-{$key}", $data);
	}
}