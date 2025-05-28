<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class TestaConexao extends Command
{
    protected $signature = 'TestaConexao';

    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        //$this->restClient = new \Someline\Rest\RestClient('nexti');
        //$this->restClient->withOAuthToken('client_credentials');
        $teste = DB::connection('sqlsrv')->select('SELECT TOP(10) * FROM "12.1.2210".dbo.SRA010');
        foreach($teste as $t){
            echo $t->RA_NOME . PHP_EOL;
        }
    }
}