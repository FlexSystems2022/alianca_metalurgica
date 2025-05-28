<?php

namespace App\Services\ViaCep\Repository;

use Illuminate\Database\Connection;

class DatabaseRepository implements RepositoryContract
{
    /**
     * Connection database
     *
     * @var Client $api
     */
    public $connection;

    /**
     * Create new instance of service
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

	/**
	 * Get data
	 * 
	 * @return mixed
	 */
	public function get(string $key)
	{
		$data = $this->connection->table(env('DB_OWNER')."CEP_AUX")
				->where('CEP', $key)
				->first('JSON');

		if(!$data || !isset($data->JSON) || !$data->JSON) {
			return null;
		}

		return json_decode($data->JSON, JSON_OBJECT_AS_ARRAY);
	}

	/**
	 * Put data
	 */
	public function put(string $key, $data): void
	{
		$ibge = null;
		if(is_array($data)) {
			$ibge = data_get($data, 'ibge') ?: null;
			$data = json_encode($data);
		}

		$this->connection->table(env('DB_OWNER')."CEP_AUX")
				->updateOrInsert(
			        ['CEP' => $key],
			        ['JSON' => $data, 'IBGE' => $ibge]
    			);
	}
}