<?php

namespace App\Console\Commands;

use SoapClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class ConciliacaoAusencias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ConciliacaoAusencias';

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

    private function WSEraseSenior()
    {
        $sqlRemove = "select
               ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID,
               ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.LASTUPDATE,
               ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID,
               ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONID,
               ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHMINUTE,
               ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTMINUTE,
               ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID,
               ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP,
               ".env('DB_OWNER')."FLEX_COLABORADOR.TIPCOL,
               ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD
               from
               ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
               inner join ".env('DB_OWNER')."FLEX_COLABORADOR
                     on (".env('DB_OWNER')."FLEX_COLABORADOR.ID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONID)
               Where ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.SITUACAO IN (0,2)
               AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 3
               AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.INTEGRA_PROTHEUS = 0
                AND not exists (select * from ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                        where ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID)";
        $data = DB::connection('sqlsrv')->select($sqlRemove);
        $snap = collect($data)->map(function ($x) {
            return (array) $x;
        })->toArray();
        foreach ($snap as $row) {
            DB::connection('sqlsrv')->table(env('DB_OWNER')."FLEX_RET_AUSENCIAS")->where("ID", $row["ID"])->update(["SITUACAO" => 1, "OBSERVACAO" => date("d/m/Y H:i:s")." - Registro Não Localizado na Senior", "INTEGRA_PROTHEUS" => 1]);
        }

        $sqlRemove2 = "select 
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.LASTUPDATE,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONID,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHMINUTE,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTMINUTE,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID,

                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.CIDCODE,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.CIDDESCRIPTION,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.CIDID,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.MEDICALDOCTORCRM,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.MEDICALDOCTORID,
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.MEDICALDOCTORNAME,

                ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP,
                ".env('DB_OWNER')."FLEX_COLABORADOR.TIPCOL,
                ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD
                from 
                ".env('DB_OWNER')."FLEX_RET_AUSENCIAS 
                inner join ".env('DB_OWNER')."FLEX_COLABORADOR
                      on (".env('DB_OWNER')."FLEX_COLABORADOR.ID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONID)
                Where ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.SITUACAO IN (0,2)
                AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 0
                AND  ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.INTEGRA_PROTHEUS = 0
                AND EXISTS (SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID)
                ORDER BY ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONID, ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTMINUTE";
        $data2 = DB::connection('sqlsrv')->select($sqlRemove2);
        $snap2 = collect($data2)->map(function ($x) {
            return (array) $x;
        })->toArray();
        foreach ($snap2 as $row2) {
            DB::connection('sqlsrv')->table(env('DB_OWNER')."FLEX_RET_AUSENCIAS")->where("ID", $row2["ID"])->update(["SITUACAO" => 1, "OBSERVACAO" => date("d/m/Y H:i:s"). " - Corrigido via API" , "INTEGRA_PROTHEUS" => 1]);
        }

        $sqlRemove3 = " SELECT 
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.LASTUPDATE,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONID,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHMINUTE,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTMINUTE,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.CIDCODE,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.CIDDESCRIPTION,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.CIDID,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.MEDICALDOCTORCRM,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.MEDICALDOCTORID,
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.MEDICALDOCTORNAME,
                        ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP,
                        ".env('DB_OWNER')."FLEX_COLABORADOR.TIPCOL,
                        ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD,
                        ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO
                        FROM 
                        ".env('DB_OWNER')."FLEX_RET_AUSENCIAS 
                        INNER JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
                            ON (".env('DB_OWNER')."FLEX_COLABORADOR.ID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONID)
                        WHERE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.SITUACAO IN (0,2)
                            AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.TIPO = 3
                            AND  ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.INTEGRA_PROTHEUS = 0
                        AND EXISTS 
                        (SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS 
                                 WHERE ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID)";
        $data3 = DB::connection('sqlsrv')->select($sqlRemove3);
        $snap3 = collect($data3)->map(function ($x) {
            return (array) $x;
        })->toArray();
        foreach ($snap3 as $row3) {
            DB::connection('sqlsrv')->table(env('DB_OWNER')."FLEX_RET_AUSENCIAS")->where("ID", $row3["ID"])->update(["SITUACAO" => 1, "OBSERVACAO" => date("d/m/Y H:i:s"). " - Corrigido via API - Conciliação (sqlRemove3)" , "INTEGRA_PROTHEUS" => 1]);
        }

        $sqlRemove4 = 
        "
            SELECT ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ID FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS
            JOIN ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS
                ON ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.PERSONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.PERSONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTDATETIME = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.FINISHDATETIME = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHDATETIME
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.FINISHMINUTE = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.FINISHMINUTE
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.STARTMINUTE = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.STARTMINUTE
                AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.ABSENCESITUATIONEXTERNALID = ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.ABSENCESITUATIONEXTERNALID
                AND ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.REMOVED = 0
            WHERE ".env('DB_OWNER')."FLEX_RET_AUSENCIAS.SITUACAO IN (0,2)
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.SITUACAO = 1
            AND ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS.TIPO = 0
        ";

        $data4 = DB::connection('sqlsrv')->select($sqlRemove4);
        $snap4 = collect($data4)->map(function ($x) {
            return (array) $x;
        })->toArray();
        foreach ($snap4 as $row4) {
            DB::connection('sqlsrv')->table(env('DB_OWNER')."FLEX_RET_AUSENCIAS")->where("ID", $row4["ID"])->update(["SITUACAO" => 1, "OBSERVACAO" => date("d/m/Y H:i:s"). " - Registro Já Existe no Senior (Eraser Sqlremod4)" , "INTEGRA_PROTHEUS" => 1]);
        }

    }
    

    public function recuperaIdTroca()
    {
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS 
            WHERE SITUACAO IN (0,2)
        ";

        $ausencias = DB::connection('sqlsrv')->select($query);

        foreach ($ausencias as $itemTroca) {
            $finishDateTime = trim($itemTroca->FINISHDATETIME);
            $finishDateTime = date("Y-m-d", strtotime($finishDateTime));
            $finishDateTime = $finishDateTime == '1900-01-01' ? '1900-12-31' : $finishDateTime;

            $consulta = 
            "
                SELECT 
                    ID
                FROM ".env('DB_OWNER')."FLEX_RET_AUSENCIAS 
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
                            ".env('DB_OWNER')."FLEX_AUSENCIAS_PROTHEUS 
                        SET ID = {$dado[0]->ID}, TIPO = 0, SITUACAO = 1, OBSERVACAO = '". date("d/m/Y H:i:s"). " - Registro Já Processado No Senior (recuperaIdTroca query)'
                        WHERE PERSONEXTERNALID = '{$itemTroca->PERSONEXTERNALID}' 
                        AND STARTDATETIME = CONVERT(DATETIME, '{$itemTroca->STARTDATETIME}', 120) 
                        AND FINISHDATETIME = CONVERT(DATETIME, '{$itemTroca->FINISHDATETIME}', 120)
                        AND FINISHMINUTE = '{$itemTroca->FINISHMINUTE}'
                        AND STARTMINUTE = '{$itemTroca->STARTMINUTE}'
                        AND ABSENCESITUATIONEXTERNALID = '{$itemTroca->ABSENCESITUATIONEXTERNALID}'";


                DB::connection('sqlsrv')->statement($query);

                $query2 = "UPDATE 
                            ".env('DB_OWNER')."FLEX_RET_AUSENCIAS 
                        SET ID = {$dado[0]->ID}, TIPO = 0, SITUACAO = 1, OBSERVACAO = '". date("d/m/Y H:i:s")." - Registro Já Processado No Senior (recuperaIdTroca query2)', INTEGRA_PROTHEUS = 1
                        WHERE ID = '{$dado[0]->ID}'";

                DB::connection('sqlsrv')->statement($query2);

            }
        }
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
        $this->recuperaIdTroca();
        $this->WSEraseSenior();
        //$this->insertLogs("Registra Ausencia foram enviadas para o Nexti.", date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));
    }
}
