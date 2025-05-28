<?php

namespace App\Console\Commands;

use SoapClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConciliacaoEscalas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ConciliacaoEscalas';

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
        $obs = date("d/m/Y H:i:s") . ' - Registros ajustados automaticamente devido estarem processados no Nexti';
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS
            SET ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.ID = ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.ID,
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.SITUACAO = 1,
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.OBSERVACAO = '{$obs}'
            FROM ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS
            JOIN ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS
                ON ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.IDEXTERNO
                AND ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.SCHEDULEEXTERNALID = ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.ESCALA
                AND ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.ROTATIONCODE = ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.TURMA
                AND CONVERT(DATE, ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.TRANSFERDATETIME, 120) = CONVERT(DATE, ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.DATALT, 120)
                AND ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.REMOVED = 0
            WHERE ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.SITUACAO IN(0,2,99)
            AND ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.TIPO = 0
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function merges()
    {   
        $obs = date("d/m/Y H:i:s") . ' - Registros ajustados automaticamente devido estarem removidos';
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS
            SET ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.SITUACAO = 1,
            ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.INTEGRA_PROTHEUS = 1,
            ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.OBSERVACAO = '{$obs}'
            WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.TIPO = 3
            AND ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.SITUACAO IN(0,2)
        ";

        DB::connection('sqlsrv')->statement($query);

        $obs = date("d/m/Y H:i:s") . ' - Registros ajustados automaticamente devido existirem no envio';
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS
            SET ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.SITUACAO = 1,
            ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.INTEGRA_PROTHEUS = 1,
            ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS.OBSERVACAO = '{$obs}'
            WHERE FLEX_RET_TROCA_ESCALAS.TIPO = 0
            AND FLEX_RET_TROCA_ESCALAS.SITUACAO IN(0,2)
            AND EXISTS(
                SELECT * FROM FLEX_TROCA_ESCALA_PROTHEUS
                WHERE FLEX_TROCA_ESCALA_PROTHEUS.IDEXTERNO = FLEX_RET_TROCA_ESCALAS.PERSONEXTERNALID
                AND CONVERT(DATE, FLEX_TROCA_ESCALA_PROTHEUS.DATALT, 120) = CONVERT(DATE, FLEX_RET_TROCA_ESCALAS.TRANSFERDATETIME, 120)
                AND FLEX_TROCA_ESCALA_PROTHEUS.ESCALA = FLEX_RET_TROCA_ESCALAS.SCHEDULEEXTERNALID
                AND FLEX_TROCA_ESCALA_PROTHEUS.TURMA = FLEX_RET_TROCA_ESCALAS.ROTATIONCODE
                AND FLEX_TROCA_ESCALA_PROTHEUS.TIPO = 0
            )
        ";

        DB::connection('sqlsrv')->statement($query);
    }
    
    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime, '{$dataInicio}', 120), convert(datetime, '{$dataFim}', 120), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
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
