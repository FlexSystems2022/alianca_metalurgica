<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaTrocaPostosAux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTrocaPostosAux';

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

    public function recuperaIdTroca()
    {
        $query = 
        "
            SELECT 
                NEXTI_TROCA_POSTO_PROTHEUS.*, 
                NEXTI_COLABORADOR.IDEXTERNO AS IDEXTERNOCOLABORADOR,
                NEXTI_POSTO.ID AS IDPOSTO
            FROM ".env('DB_OWNER')."NEXTI_TROCA_POSTO_PROTHEUS
            JOIN ".env('DB_OWNER')."NEXTI_POSTO
                ON (NEXTI_POSTO.ESTPOS = NEXTI_TROCA_POSTO_PROTHEUS.ESTPOS
                    AND NEXTI_POSTO.POSTRA = NEXTI_TROCA_POSTO_PROTHEUS.POSTRA
                    AND NEXTI_POSTO.NUMEMP = NEXTI_TROCA_POSTO_PROTHEUS.NUMEMP
                    AND NEXTI_POSTO.CODFIL = NEXTI_TROCA_POSTO_PROTHEUS.CODFIL
                    AND NEXTI_POSTO.ID > 0
                ) 
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR 
                ON (NEXTI_COLABORADOR.NUMEMP = NEXTI_TROCA_POSTO_PROTHEUS.NUMEMP
                    AND NEXTI_COLABORADOR.CODFIL = NEXTI_TROCA_POSTO_PROTHEUS.CODFIL
                    AND NEXTI_COLABORADOR.TIPCOL = NEXTI_TROCA_POSTO_PROTHEUS.TIPCOL
                    AND NEXTI_COLABORADOR.NUMCAD = NEXTI_TROCA_POSTO_PROTHEUS.NUMCAD
                )
            WHERE NEXTI_TROCA_POSTO_PROTHEUS.SITUACAO IN(0,2,99)
            AND NEXTI_TROCA_POSTO_PROTHEUS.TIPO = 0
        ";
        
        $trocas = DB::connection('sqlsrv')->select($query);

        $count = 1;
        foreach ($trocas as $itemTroca) { 
            var_dump($count);  
            $count++;
            $consulta = 
            "
                SELECT 
                    ID
                FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS 
                WHERE NEXTI_RET_TROCA_POSTOS.PERSONEXTERNALID = '{$itemTroca->IDEXTERNOCOLABORADOR}' 
                AND CONVERT(DATETIME, NEXTI_RET_TROCA_POSTOS.TRANSFERDATETIME, 120) = CONVERT(DATETIME, '{$itemTroca->INIATU}', 120) 
                AND NEXTI_RET_TROCA_POSTOS.WORKPLACEID = '{$itemTroca->IDPOSTO}'
                AND NEXTI_RET_TROCA_POSTOS.TIPO = 0
            ";
            $dado = DB::connection('sqlsrv')->select($consulta);

            if (sizeof($dado) > 0) {
                var_dump('existe');
                $obs = date("d/m/Y H:i:s") . ' - ALTERADO VIA SCRIPT';
                $query = 
                "
                    UPDATE 
                    ".env('DB_OWNER')."NEXTI_TROCA_POSTO_PROTHEUS 
                    SET ID = {$dado[0]->ID}, 
                    TIPO = 0, 
                    SITUACAO = 1,
                    OBSERVACAO = '{$obs}'
                    WHERE ".env('DB_OWNER')."NEXTI_TROCA_POSTO_PROTHEUS.NUMEMP = {$itemTroca->NUMEMP}
                    AND ".env('DB_OWNER')."NEXTI_TROCA_POSTO_PROTHEUS.CODFIL = '{$itemTroca->CODFIL}'
                    AND ".env('DB_OWNER')."NEXTI_TROCA_POSTO_PROTHEUS.TIPCOL = {$itemTroca->TIPCOL}
                    AND ".env('DB_OWNER')."NEXTI_TROCA_POSTO_PROTHEUS.NUMCAD = '{$itemTroca->NUMCAD}' 
                    AND ".env('DB_OWNER')."NEXTI_TROCA_POSTO_PROTHEUS.INIATU = CONVERT(DATETIME, '{$itemTroca->INIATU}', 120)
                    AND ".env('DB_OWNER')."NEXTI_TROCA_POSTO_PROTHEUS.ESTPOS = {$itemTroca->ESTPOS}
                    AND ".env('DB_OWNER')."NEXTI_TROCA_POSTO_PROTHEUS.POSTRA = '{$itemTroca->POSTRA}' 
                    AND ".env('DB_OWNER')."NEXTI_TROCA_POSTO_PROTHEUS.CODCCU = '{$itemTroca->CODCCU}'
                ";

                DB::connection('sqlsrv')->statement($query);
            }
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

        $this->recuperaIdTroca();

    }
}
