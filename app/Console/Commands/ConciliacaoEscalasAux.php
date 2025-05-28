<?php

namespace App\Console\Commands;

use SoapClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConciliacaoEscalasAux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ConciliacaoEscalasAux';

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

    public function atualizaIdRegistro()
    {
        $sql = "
            SELECT 
            ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.*
            FROM ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX 
            WHERE NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO IN (0,2)
            AND NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TIPO = 0
        ";
        $trocaEscalas = DB::connection('sqlsrv')->select($sql);

        $count = 0;
        foreach ($trocaEscalas as $itemTroca) {
            $dataCompIni = explode(' ', $itemTroca->DATALT)[0];
            $query = 
            "
                SELECT 
                    ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.*
                FROM NEXTI_RET_TROCA_ESCALAS 
                WHERE NEXTI_RET_TROCA_ESCALAS.PERSONEXTERNALID = '{$itemTroca->IDEXTERNO}'
                AND NEXTI_RET_TROCA_ESCALAS.SCHEDULEEXTERNALID = '{$itemTroca->ESCALA}'
                AND NEXTI_RET_TROCA_ESCALAS.TRANSFERDATETIME = CONVERT(DATETIME, '{$itemTroca->DATALT}', 120)
                AND NEXTI_RET_TROCA_ESCALAS.ROTATIONCODE = {$itemTroca->TURMA}
                AND NEXTI_RET_TROCA_ESCALAS.REMOVED = 0
            ";
            
            $dado = DB::connection('sqlsrv')->select($query);

            var_dump($count++);

            if (sizeof($dado) > 0) {
                $obs = date("d/m/Y") . ' - CORRIGIDO VIA API';
                foreach ($dado as $itemDado) {
                    $dataCompFim = explode(' ', $itemDado->TRANSFERDATETIME)[0];
                    $dataCompIni = date('Y-m-d', strtotime($dataCompIni));

                    if ($dataCompIni == $dataCompFim) {
                        $query =
                        "UPDATE 
                            ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX 
                        SET ID = {$itemDado->ID}, 
                        OBSERVACAO = '{$obs}', 
                        SITUACAO = 1, 
                        TIPO = 0 
                        WHERE NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT = '{$itemTroca->DATALT}' 
                        AND NEXTI_TROCA_ESCALA_PROTHEUS_AUX.IDEXTERNO = '{$itemTroca->IDEXTERNO}' 
                        AND NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ESCALA = '{$itemTroca->ESCALA}'
                        AND NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TURMA = '{$itemTroca->TURMA}'";
                        DB::connection('sqlsrv')->statement($query);

                        $query2 = 
                        "
                            UPDATE 
                            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS 
                            SET ID = {$dado[0]->ID}, 
                            SITUACAO = 1, 
                            OBSERVACAO = '". date("d/m/Y H:i:s")." - CORRIGIDO VIA API (recuperaIdTroca query2)', 
                            INTEGRA_PROTHEUS = 1
                            WHERE ID = '{$dado[0]->ID}'
                        ";

                        DB::connection('sqlsrv')->statement($query2);
                    }
                }
            }
        }
    }

    private function merges()
    {   
        $obs = date("d/m/Y H:i:s") . ' - Registros ajustados automaticamente devido estarem removidos';
        $query = 
        "
            UPDATE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS
            SET ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.SITUACAO = 1,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.INTEGRA_PROTHEUS = 1,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.OBSERVACAO = '{$obs}'
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.TIPO = 3
            AND ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.SITUACAO IN(0,2)
        ";

        DB::connection('sqlsrv')->statement($query);

        $obs = date("d/m/Y H:i:s") . ' - Registros ajustados automaticamente devido existirem no envio';
        $query = 
        "
            UPDATE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS
            SET ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.SITUACAO = 1,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.INTEGRA_PROTHEUS = 1,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.OBSERVACAO = '{$obs}'
            WHERE NEXTI_RET_TROCA_ESCALAS.TIPO = 0
            AND NEXTI_RET_TROCA_ESCALAS.SITUACAO IN(0,2)
            AND EXISTS(
                SELECT * FROM ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX
                WHERE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.IDEXTERNO = ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT = ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.TRANSFERDATETIME
                AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ESCALA = ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.SCHEDULEEXTERNALID
                AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TURMA = ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS.ROTATIONCODE
                AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TIPO = 0
            )
        ";

        DB::connection('sqlsrv')->statement($query);
    }
    
    
    /**
     * Execute the console command.
     *
     * @param  null
     * @return mixed
     */
    public function handle()
    {   
        $this->atualizaIdRegistro();
        $this->merges();
    }
}
