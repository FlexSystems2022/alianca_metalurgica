<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaPostosTerminais extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaPostosTerminais';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $restClient = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');
        parent::__construct();
        $this->query = "";
    }

    private function getResponseErrors()
    {
        $responseData = $this->restClient->getResponseData();
       
        $message = isset($responseData['message']) ? $responseData['message'] : '';
        $comments = '';
        if (isset($responseData['comments']) && sizeof($responseData['comments']) > 0) {
            $comments = $responseData['comments'][0];
        }
        
        if ($comments != '') {
            $message = $comments;
        }

        return $message;
    }

    private function getResponseValue()
    {
        $responseData = $this->restClient->getResponseData();
        return $responseData;
    }

    private function buscaPostos(){
        $query = 
        "   
            SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_POSTO
        "; 

        return DB::connection('sqlsrv')->select($query);
    }

    private function buscaTerminais()
    {
        $postos = $this->buscaPostos();
        $count = 0;
        foreach ($postos as $posto) {
            $this->buscaTerminalRecursivo(0, $posto);
            $count++;
            var_dump($count);
        }   
    }


    private function buscaTerminalRecursivo($page = 0, $posto)
    {
        $response = $this->restClient->get("devices/workplace/{$posto->ID}", [
            'page' => $page,
            'size' => 10000,
            //'workplaceId' => $posto->ID,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar terminais: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            if (isset($responseData['content'])) {
                $this->addTerminal($responseData['content'], $posto);
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function addTerminal($terminais, $posto)
    {   
        $this->inserirTerminais($posto, $terminais);
    }

    private function inserirTerminais($posto, $terminais){

        foreach($terminais as $terminal){
            $active = isset($terminal['code']) && $terminal['code'] == true ? 1 : 0;
            $queryInsereTerminais = 
            "
                INSERT INTO ".env('DB_OWNER')."NEXTI_RET_POSTO_TERMINAIS
                (
                    IDPOSTO, CODE, ACTIVE
                )
                VALUES
                (
                    {$posto->ID}, '{$terminal['code']}', {$active}
                )
            ";

            DB::connection('sqlsrv')->statement($queryInsereTerminais);
        }
    }

    private function deleta(){     
        $queryDeleteTerminais =
        "
            DELETE FROM ".env('DB_OWNER')."NEXTI_RET_POSTO_TERMINAIS
        ";

        DB::connection('sqlsrv')->statement($queryDeleteTerminais);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->deleta();
        $this->buscaTerminais();
    }
}