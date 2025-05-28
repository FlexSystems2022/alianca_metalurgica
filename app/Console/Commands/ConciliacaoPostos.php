<?php

namespace App\Console\Commands;

use SoapClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConciliacaoPostos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ConciliacaoPostos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Concilia Registros Entre Envio e Retorno Senior x Nexti.';
    protected $client;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function merges(){
        $obs = date("d/m/Y H:i:s") . ' - Registros ajustados automaticamente devido estarem removidos';
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS
            SET ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.SITUACAO = 1,
            ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.INTEGRA_PROTHEUS = 1,
            ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.OBSERVACAO = '{$obs}'
            WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.TIPO = 3
            AND ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.SITUACAO IN(0,2)
        ";

        DB::connection('sqlsrv')->statement($query);

        $obs = date("d/m/Y H:i:s") . ' - Registros ajustados automaticamente devido existirem no envio';
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS
            SET ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.SITUACAO = 1,
            ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.INTEGRA_PROTHEUS = 1,
            ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.OBSERVACAO = '{$obs}'
            FROM ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS
            JOIN ".env('DB_OWNER')."FLEX_POSTO
                ON ".env('DB_OWNER')."FLEX_POSTO.IDEXTERNO = ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.WORKPLACEEXTERNALID
            WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.SITUACAO IN(0,2)
            AND ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.TIPO = 0
            AND EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS
                WHERE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.POSTRA = ".env('DB_OWNER')."FLEX_POSTO.POSTRA
                AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.CODCCU = ".env('DB_OWNER')."FLEX_POSTO.CODCCU
                AND CONVERT(DATE, ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.INIATU, 120) = CONVERT(DATE, ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.TRANSFERDATETIME, 120)
                AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.IDEXTERNO = ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.TIPO = 0
            )
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    public function recuperaIdTroca()
    {
        $query = 
        "
            SELECT 
                FLEX_TROCA_POSTO_PROTHEUS.*, 
                FLEX_COLABORADOR.IDEXTERNO AS IDEXTERNOCOLABORADOR,
                FLEX_POSTO.ID AS IDPOSTO
            FROM ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS
            JOIN ".env('DB_OWNER')."FLEX_POSTO
                ON (
                    FLEX_POSTO.CODCCU = FLEX_TROCA_POSTO_PROTHEUS.CODCCU
                    AND FLEX_POSTO.POSTRA = FLEX_TROCA_POSTO_PROTHEUS.POSTRA
                    AND FLEX_POSTO.NUMEMP = FLEX_TROCA_POSTO_PROTHEUS.NUMEMP
                    AND FLEX_POSTO.ID > 0
                ) 
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR 
                ON (
                    FLEX_COLABORADOR.IDEXTERNO = FLEX_TROCA_POSTO_PROTHEUS.IDEXTERNO
                )
            WHERE FLEX_TROCA_POSTO_PROTHEUS.SITUACAO IN(0,2)
            AND FLEX_TROCA_POSTO_PROTHEUS.TIPO = 0
        ";
        
        $trocas = DB::connection('sqlsrv')->select($query);

        foreach ($trocas as $itemTroca) {
            $consulta = 
            "
                SELECT 
                    ID
                FROM ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS 
                WHERE FLEX_RET_TROCA_POSTOS.PERSONEXTERNALID = '{$itemTroca->IDEXTERNOCOLABORADOR}' 
                AND CONVERT(DATETIME, FLEX_RET_TROCA_POSTOS.TRANSFERDATETIME, 120) = CONVERT(DATETIME, '{$itemTroca->INIATU}', 120) 
                AND FLEX_RET_TROCA_POSTOS.WORKPLACEID = '{$itemTroca->IDPOSTO}'
                AND FLEX_RET_TROCA_POSTOS.TIPO = 0
            ";
            $dado = DB::connection('sqlsrv')->select($consulta);

            if (sizeof($dado) > 0) {
                var_dump('existe');
                $obs = date("d/m/Y H:i:s") . ' - ALTERADO VIA SCRIPT';
                $query = 
                "
                    UPDATE 
                    ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS 
                    SET ID = {$dado[0]->ID}, 
                    TIPO = 0, 
                    SITUACAO = 1,
                    OBSERVACAO = '{$obs}'
                    WHERE ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.IDEXTERNO = {$itemTroca->IDEXTERNO} 
                    AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.INIATU = CONVERT(DATETIME, '{$itemTroca->INIATU}', 120)
                    AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.POSTRA = '{$itemTroca->POSTRA}' 
                    AND ".env('DB_OWNER')."FLEX_TROCA_POSTO_PROTHEUS.CODCCU = '{$itemTroca->CODCCU}'
                ";

                DB::connection('sqlsrv')->statement($query);
            }
        }
    }
    
    /**
     * Execute the console command.
     *
     * @param  null
     * @return mixed
     */
    public function handle()
    {
        $this->recuperaIdTroca();
        $this->merges();
    }
}
