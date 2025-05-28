<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class ProcessaTrocaSindicatosAux extends Command
{
    protected $signature = 'ProcessaTrocaSindicatosAux';
    
    protected $description = 'Command description';

    private $restClient = null;

    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');

        parent::__construct();
    }

    private function getResponseErrors()
    {
        $responseData = $this->restClient->getResponseData();
       
        $message = $responseData['message']??'';
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


    private function getTrocaSindicatoExcluir()
    {
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER').".dbo.NEXTI_RET_TROCA_SINDICATOS
            WHERE NOT EXISTS(
                SELECT * FROM ".env('DB_OWNER').".dbo.NEXTI_TROCA_SINDICATO_SENIOR
                WHERE ".env('DB_OWNER').".dbo.NEXTI_TROCA_SINDICATO_SENIOR.ID = ".env('DB_OWNER').".dbo.NEXTI_RET_TROCA_SINDICATOS.ID
            )
        ";

        $trocaEscalas = DB::connection('sqlsrv')->select($query);

        return $trocaEscalas;
    }


    private function processaTrocaSindicatoExcluir()
    {
        $trocaSindicato = $this->getTrocaSindicatoExcluir();
        $nTrocaSindicato = sizeof($trocaSindicato);
        $this->info("Iniciando exclusão de dados ({$nTrocaSindicato} trocas de escalas)");

        foreach ($trocaSindicato as $trocaSindicatoItem) {
            $this->enviaTrocaSindicatoExcluir($trocaSindicatoItem);
        }
        $this->info("Total de {$nTrocaSindicato} troca sindicato(s) cadastrados!");
    }

    public function enviaTrocaSindicatoExcluir($trocaSindicato)
    {
        $this->info("Excluindo troca de sindicato: {$trocaSindicato->ID}");

        $response = $this->restClient->delete("tradeunionstransfers/{$trocaSindicato->ID}", [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();

            $this->info("Problema excluir troca sindicado {$trocaSindicato->ID}: {$errors}");
        } else {
            $this->info("Exclusão de troca de sindicato: {$trocaSindicato->ID} enviada com sucesso.");
        }
    }

    public function handle()
    {
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $retTrocaEscalasNovos = $this->processaTrocaSindicatoExcluir();
    }
}
