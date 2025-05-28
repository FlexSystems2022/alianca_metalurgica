<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaAusenciasAux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaAusenciasAux';

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
            SELECT * FROM ".env('DB_OWNER')."NEXTI_AUSENCIAS_PROTHEUS_AUX
        ";
    }

    public function recuperaIdTroca()
    {
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."NEXTI_AUSENCIAS_PROTHEUS 
            WHERE SITUACAO > 2
        ";

        $ausencias = DB::connection('sqlsrv')->select($query);

        $count = 1;

        foreach ($ausencias as $itemTroca) {
            var_dump($count);
            $count++;
            
            $finishDateTime = trim($itemTroca->FINISHDATETIME);
            $finishDateTime = date("Y-m-d", strtotime($finishDateTime));
            $finishDateTime = $finishDateTime == '1900-01-01' ? '1900-12-31' : $finishDateTime;

            $consulta = 
            "
                SELECT 
                    ID
                FROM ".env('DB_OWNER')."NEXTI_RET_AUSENCIAS 
                WHERE PERSONEXTERNALID = '{$itemTroca->PERSONEXTERNALID}' 
                AND STARTDATETIME = CONVERT(DATETIME, '{$itemTroca->STARTDATETIME}', 120) 
                AND FINISHDATETIME = CONVERT(DATETIME, '{$finishDateTime}', 120)
                AND FINISHMINUTE = '{$itemTroca->FINISHMINUTE}'
                AND STARTMINUTE = '{$itemTroca->STARTMINUTE}'
                AND ABSENCESITUATIONEXTERNALID = '{$itemTroca->ABSENCESITUATIONEXTERNALID}'
                AND TIPO = 0
            ";

            //var_dump($consulta);exit;

            $dado = DB::connection('sqlsrv')->select($consulta);

            if (sizeof($dado) > 0) {
                $query = "UPDATE 
                            ".env('DB_OWNER')."NEXTI_AUSENCIAS_PROTHEUS 
                        SET ID = {$dado[0]->ID}, TIPO = 0, SITUACAO = 1, OBSERVACAO = '". date("d/m/Y H:i:s"). " - Registro Já Processado No Senior (recuperaIdTroca query)'
                        WHERE PERSONEXTERNALID = '{$itemTroca->PERSONEXTERNALID}' 
                        AND STARTDATETIME = CONVERT(DATETIME, '{$itemTroca->STARTDATETIME}', 120) 
                        AND FINISHDATETIME = CONVERT(DATETIME, '{$itemTroca->FINISHDATETIME}', 120)
                        AND FINISHMINUTE = '{$itemTroca->FINISHMINUTE}'
                        AND STARTMINUTE = '{$itemTroca->STARTMINUTE}'
                        AND ABSENCESITUATIONEXTERNALID = '{$itemTroca->ABSENCESITUATIONEXTERNALID}'";


                DB::connection('sqlsrv')->statement($query);

                $query2 = "UPDATE 
                            ".env('DB_OWNER')."NEXTI_RET_AUSENCIAS 
                        SET ID = {$dado[0]->ID}, TIPO = 0, SITUACAO = 1, OBSERVACAO = '". date("d/m/Y H:i:s")." - Registro Já Processado No Senior (recuperaIdTroca query2)', INTEGRA_PROTHEUS = 1
                        WHERE ID = '{$dado[0]->ID}'";

                DB::connection('sqlsrv')->statement($query2);

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
        $this->recuperaIdTroca();
    }
}
