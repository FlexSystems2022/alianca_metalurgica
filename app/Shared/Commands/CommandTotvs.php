<?php

namespace App\Shared\Commands;

use App\Totvs\Client;
use Illuminate\Console\Command;

abstract class CommandTotvs extends Command
{
    protected string $owner;

    protected Client $client;

    public function __construct() {
        parent::__construct();

        $this->client = new Client();
    }
}