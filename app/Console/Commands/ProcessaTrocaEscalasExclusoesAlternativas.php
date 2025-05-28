<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaTrocaEscalasExclusoesAlternativas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTrocaEscalasExclusoesAlternativas';

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


    private function insert(){
        $query = 
        "
            INSERT INTO ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES
            SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS a
            WHERE a.REMOVED = 0 
            AND EXISTS(
                SELECT * FROM ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX b 
                WHERE b.IDEXTERNO = a.PERSONEXTERNALID
                AND b.DATALT = a.TRANSFERDATETIME
                AND b.TIPO = 0
                AND b.SITUACAO IN(0,2)
            )
            AND NOT EXISTS(
                SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES c
                WHERE c.ID =  a.ID
            )
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function atualizaRegistro($trocaEscala)
    {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("
            UPDATE NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES
            SET ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.SITUACAO = 99, 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.OBSERVACAO = '{$obs}'
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.ID = {$trocaEscala->ID}
        ");
    }

    private function atualizaRegistroComErro($trocaEscala, $obs = '')
    {   
        $obs = str_replace("'", '', $obs);
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES 
            SET ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.SITUACAO = 2, 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.ID = {$trocaEscala->ID}
        ");
    }

    private function getTrocaEscalasExcluir()
    {
        $query = 
        " 
            SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.SITUACAO NOT IN(99)
            ORDER BY ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.TRANSFERDATETIME DESC
        ";
        
        return DB::connection('sqlsrv')->select($query);
    }


    private function processaTrocaEscalasExcluir()
    {
        $trocaEscalas = $this->getTrocaEscalasExcluir();
        $nTrocaEscalas = sizeof($trocaEscalas);
        $this->info("Iniciando exclusão de dados ({$nTrocaEscalas} trocas de escalas)");

        foreach ($trocaEscalas as $trocaEscala) {
            $ret = $this->enviaTrocaEscalaExcluir($trocaEscala);
        }
    }

    private function enviaTrocaEscalaExcluir($trocaEscala)
    {
        $this->info("Excluindo troca de escala {$trocaEscala->ID}");

        $response = $this->restClient->delete("scheduletransfers/" . $trocaEscala->ID, [], [])->getResponse();
        
        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema troca de escala {$trocaEscala->ID}: {$errors}");

            $this->atualizaRegistroComErro($trocaEscala,$errors);
        } else {
            $this->info("Exclusão de troca de escala {$trocaEscala->ID}");

            $this->atualizaRegistro($trocaEscala);
        }
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

        $this->insert();
        $this->processaTrocaEscalasExcluir();
    }
}
