<?php

namespace App\Shared\Commands;

use Illuminate\Console\Command;
use App\Shared\Provider\RestClient;

abstract class CommandNexti extends Command
{
    protected string $owner;

    protected RestClient $client;

    public function __construct() {
        parent::__construct();

        $this->owner = env('DB_OWNER', '');
    }

    protected function client(): RestClient
    {
        $this->client = new RestClient('nexti');
        $this->client->withOAuthToken('client_credentials');

        return $this->client;
    }
}