<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaSaneamentoTrocaEscalas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaSaneamentoTrocaEscalas';

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
        $this->query =  
        "
            SELECT * FROM NEXTI_RET_TROCA_ESCALAS
            WHERE TIPO <> 3
            AND EXISTS(
                SELECT
                    1
                FROM NEXTI_COLABORADOR
                WHERE NEXTI_COLABORADOR.ID = NEXTI_RET_TROCA_ESCALAS.PERSONID
            )
            AND EXISTS(
                SELECT
                    1
                FROM NEXTI_TROCA_ESCALA_PROTHEUS_AUX
                JOIN NEXTI_COLABORADOR
                    ON(NEXTI_COLABORADOR.NUMEMP = NEXTI_TROCA_ESCALA_PROTHEUS_AUX.NUMEMP
                        AND NEXTI_COLABORADOR.CODFIL = NEXTI_TROCA_ESCALA_PROTHEUS_AUX.CODFIL
                        AND NEXTI_COLABORADOR.TIPCOL = NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TIPCOL
                        AND NEXTI_COLABORADOR.NUMCAD = NEXTI_TROCA_ESCALA_PROTHEUS_AUX.NUMCAD
                    )
                WHERE NEXTI_COLABORADOR.ID = NEXTI_RET_TROCA_ESCALAS.PERSONID
                AND NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT = NEXTI_RET_TROCA_ESCALAS.TRANSFERDATETIME
                AND NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ID <> NEXTI_RET_TROCA_ESCALAS.ID
                AND NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TIPO <> 3
                AND NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO <> 99
            )
            AND USERREGISTERID = 355717
        ";
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

    private function getTrocaEscalasExcluir()
    {
        return DB::connection('sqlsrv')->select($this->query);
    }


    private function processaTrocaEscalasExcluir()
    {
        $trocaEscalas = $this->getTrocaEscalasExcluir();
        $nTrocaEscalas = sizeof($trocaEscalas);
        $this->info("Iniciando exclusão de dados ({$nTrocaEscalas} trocas de escalas)");

        foreach ($trocaEscalas as $trocaEscala) {
            $trocaEscalaNexti = [
                'id' => $trocaEscala->ID
            ];
            $ret = $this->enviaTrocaEscalaExcluir($trocaEscalaNexti, $trocaEscala);
        }
        $this->info("Total de {$nTrocaEscalas} trocaEscala(s) cadastrados!");
    }

    private function enviaTrocaEscalaExcluir($trocaEscala, $trocaOriginal)
    {
        $scheduleExternalId ='';
        $idCol = '';

        $this->info("Excluindo troca de escala: {$scheduleExternalId} para colaborador {$idCol}");

        $response = $this->restClient->delete("scheduletransfers/" . $trocaEscala['id'], [], [])->getResponse();
        
        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema troca de escala: {$scheduleExternalId} para colaborador {$idCol}: {$errors}");
        } else {
            $this->info("Exclusão de troca de escala: {$scheduleExternalId} para colaborador {$idCol} enviada com sucesso.");
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

        $this->processaTrocaEscalasExcluir();
    }
}
