<?php

namespace App\Services\ViaCep\Repository;

use Illuminate\Support\Facades\DB;

class RepositoryManager
{
    /**
     * Get Repository
     */
    public static function factory(string $driver): RepositoryContract
    {
    	if('cache' === $driver) {
    		return new CacheRepository();
    	}

    	if('database' === $driver) {
    		return new DatabaseRepository(DB::connection('sqlsrv'));
    	}

    	throw new \Exception('Invalid driver');
    }
}