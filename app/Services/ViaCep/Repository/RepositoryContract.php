<?php

namespace App\Services\ViaCep\Repository;

interface RepositoryContract
{
	/**
	 * Get data
	 * 
	 * @return mixed
	 */
	public function get(string $key);

	/**
	 * Put data
	 */
	public function put(string $key, $data): void;
}