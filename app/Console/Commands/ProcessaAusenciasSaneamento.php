<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaAusenciasSaneamento extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaAusenciasSaneamento';

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
    
    private function getAusenciasExcluir()
    {
        // $query = 
        // " 
            // SELECT * FROM NEXTI_RET_AUSENCIAS
            // WHERE EXISTS(
            //     SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS
            //     WHERE NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
            //     AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME = NEXTI_RET_AUSENCIAS.STARTDATETIME
            //     AND NEXTI_AUSENCIAS_PROTHEUS.IDEXTERNO = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
            //     AND NEXTI_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
            //     AND NEXTI_AUSENCIAS_PROTHEUS.FINISHDATETIME = CONVERT(DATETIME, '1900-01-01', 120)
            // )
            // AND ABSENCESITUATIONEXTERNALID NOT IN('01-001')
            // AND NEXTI_RET_AUSENCIAS.TIPO = 0
        // ";

        // $query = 
        // " 
        //     SELECT * FROM NEXTI_RET_AUSENCIAS
        //     WHERE EXISTS(
        //         SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS
        //         WHERE NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //         AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME = NEXTI_RET_AUSENCIAS.STARTDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.IDEXTERNO = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
        //         AND NEXTI_AUSENCIAS_PROTHEUS.FINISHDATETIME <> NEXTI_RET_AUSENCIAS.FINISHDATETIME
        //     )
        //     AND ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //     AND NEXTI_RET_AUSENCIAS.TIPO = 0
        // ";

        // $query = 
        // " 
        //     SELECT
        //     *
        //     FROM NEXTI_RET_AUSENCIAS
        //     WHERE NEXTI_RET_AUSENCIAS.TIPO = 0
        //     AND EXISTS(
        //         SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS
        //         WHERE NEXTI_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME <= NEXTI_RET_AUSENCIAS.STARTDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.FINISHDATETIME >= NEXTI_RET_AUSENCIAS.FINISHDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
        //         AND NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //     )
        //     AND NEXTI_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        // ";

        // $query = 
        // " 
        //     SELECT
        //     *
        //     FROM NEXTI_RET_AUSENCIAS
        //     WHERE NEXTI_RET_AUSENCIAS.TIPO = 0
        //     AND EXISTS(
        //         SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS
        //         WHERE NEXTI_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME >= NEXTI_RET_AUSENCIAS.STARTDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME <= NEXTI_RET_AUSENCIAS.FINISHDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.FINISHDATETIME >= NEXTI_RET_AUSENCIAS.FINISHDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
        //         AND NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //     )
        //     AND NEXTI_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        // ";

        // $query = 
        // " 
        //     SELECT
        //     *
        //     FROM NEXTI_RET_AUSENCIAS
        //     WHERE NEXTI_RET_AUSENCIAS.TIPO = 0
        //     AND EXISTS(
        //         SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS
        //         WHERE NEXTI_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME >= NEXTI_RET_AUSENCIAS.STARTDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME <= NEXTI_RET_AUSENCIAS.FINISHDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.FINISHDATETIME = CONVERT(DATETIME, '1900-01-01', 120)
        //         AND NEXTI_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
        //         AND NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //     )
        //     AND NEXTI_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        // ";

        // $query = 
        // " 
        //     SELECT * FROM NEXTI_RET_AUSENCIAS
        //     WHERE EXISTS(
        //         SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS
        //         WHERE NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //         AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME <= NEXTI_RET_AUSENCIAS.STARTDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.IDEXTERNO = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
        //         AND NEXTI_AUSENCIAS_PROTHEUS.FINISHDATETIME = CONVERT(DATETIME, '1900-01-01', 120)
        //     )
        //     AND ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //     AND NEXTI_RET_AUSENCIAS.TIPO = 0
        // ";

        // $query = 
        // " 
        //     SELECT * FROM NEXTI_RET_AUSENCIAS
        //     WHERE EXISTS(
        //         SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS
        //         WHERE NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //         AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME >= NEXTI_RET_AUSENCIAS.STARTDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.IDEXTERNO = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
        //         AND NEXTI_AUSENCIAS_PROTHEUS.FINISHDATETIME = CONVERT(DATETIME, '1900-01-01', 120)
        //     )
        //     AND ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //     AND FINISHDATETIME = CONVERT(DATETIME, '1900-12-31', 120)
        //     AND NEXTI_RET_AUSENCIAS.TIPO = 0
        //     ORDER BY NEXTI_RET_AUSENCIAS.LASTUPDATE ASC
        // ";

        // $query = 
        // " 
        //     SELECT * FROM NEXTI_RET_AUSENCIAS
        //     WHERE EXISTS(
        //         SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS
        //         WHERE NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //         AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME >= NEXTI_RET_AUSENCIAS.STARTDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.IDEXTERNO = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS.SITUACAO IN(0,2)
        //     )
        //     AND ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //     AND FINISHDATETIME = CONVERT(DATETIME, '1900-12-31', 120)
        //     AND NEXTI_RET_AUSENCIAS.TIPO = 0
        //     ORDER BY NEXTI_RET_AUSENCIAS.LASTUPDATE ASC
        // ";

        // $query = 
        // "
        //     SELECT * FROM NEXTI_RET_AUSENCIAS
        //     WHERE EXISTS(
        //         SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS_AUX
        //         WHERE NEXTI_AUSENCIAS_PROTHEUS_AUX.SITUACAO IN(0,2)
        //         AND NEXTI_AUSENCIAS_PROTHEUS_AUX.ABSENCESITUATIONEXTERNALID = '999'
        //         AND NEXTI_AUSENCIAS_PROTHEUS_AUX.PERSONEXTERNALID = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS_AUX.STARTDATETIME >= NEXTI_RET_AUSENCIAS.STARTDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS_AUX.STARTDATETIME <= NEXTI_RET_AUSENCIAS.FINISHDATETIME
        //     )
        //     AND TIPO = 0
        // ";

        // $query = 
        // "
        //     SELECT * FROM NEXTI_RET_AUSENCIAS
        //     WHERE EXISTS(
        //         SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS_AUX
        //         WHERE NEXTI_AUSENCIAS_PROTHEUS_AUX.SITUACAO IN(0,2)
        //         AND NEXTI_AUSENCIAS_PROTHEUS_AUX.ABSENCESITUATIONEXTERNALID = '999'
        //         AND NEXTI_AUSENCIAS_PROTHEUS_AUX.PERSONEXTERNALID = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS_AUX.STARTDATETIME >= NEXTI_RET_AUSENCIAS.STARTDATETIME
        //         AND NEXTI_RET_AUSENCIAS.FINISHDATETIME = CONVERT(DATETIME, '1900-12-31', 120)
        //     )
        //     AND NEXTI_RET_AUSENCIAS.TIPO = 0
        // ";

        // $query = 
        // "
        //     SELECT * FROM NEXTI_RET_AUSENCIAS
        //     WHERE EXISTS(
        //         SELECT * FROM NEXTI_AUSENCIAS_PROTHEUS
        //         WHERE NEXTI_AUSENCIAS_PROTHEUS.IDEXTERNO = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME = NEXTI_RET_AUSENCIAS.STARTDATETIME
        //         AND NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = NEXTI_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID
        //         AND NEXTI_AUSENCIAS_PROTHEUS.SITUACAO IN(2)
        //         AND NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        //     )
        //     AND NEXTI_RET_AUSENCIAS.TIPO = 0
        //     AND NEXTI_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID NOT IN('01-001')
        // ";

        $ausencias = DB::connection('sqlsrv')->select($query);

        return $ausencias;
    }

    private function enviarAusenciasExcluir()
    {
        $ausencias = $this->getAusenciasExcluir();
        $nAusencias = sizeof($ausencias);
        $this->info("Iniciando exclusão de dados ({$nAusencias} ausências)");

        foreach ($ausencias as $itemAusencia) {
            $ret = $this->enviaAusenciaExcluir($itemAusencia);
        }

        $this->info("Total de {$nAusencias} ausência(s) cadastrados!");
    }

    private function enviaAusenciaExcluir($ausencia)
    {
        $this->info("Excluindo ausência: {$ausencia->PERSONEXTERNALID}");
        $response = $this->restClient->delete("absences/" . $ausencia->ID, [], [])->getResponse();
        
        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ausencia: {$ausencia->PERSONEXTERNALID}: {$errors}");
        } else {
            $this->info("Exclusão de ausência: {$ausencia->PERSONEXTERNALID} excluída com sucesso.");
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dataInicio = date('Y-m-d H:i:s A');
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');
        $this->enviarAusenciasExcluir();
    }
}
