<?php

namespace App\Console\Commands;

use SoapClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class ConciliacaoSindicatos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ConciliacaoSindicatos';

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

    public function recuperaIdTroca()
    {
        $query = 
        "
            SELECT  
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.*,  
            ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO 
            FROM ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS
            JOIN  ".env('DB_OWNER')."FLEX_COLABORADOR 
                ON   ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO =  ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.IDEXTERNO
            WHERE  ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO IN (0,2)
        ";

        $ausencias = DB::connection('sqlsrv')->select($query);

        foreach ($ausencias as $itemTroca) {
            $consulta = 
            "
                SELECT 
                    ID
                FROM  ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS 
                WHERE PERSONEXTERNALID = '{$itemTroca->IDEXTERNO}' 
                AND TRANSFERDATE = '{$itemTroca->DATALT}' 
            ";

            $dado = DB::connection('sqlsrv')->select($consulta);

            if (sizeof($dado) > 0) {
                $query = 
                "
                    UPDATE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS 
                    SET 
                    ID = {$dado[0]->ID}, 
                    TIPO = 0, 
                    SITUACAO = 1, 
                    OBSERVACAO = '". date("d/m/Y H:i:s"). " - Registro Já Processado No Senior (recuperaIdTroca query)'
                    WHERE ID = '{$itemTroca->ID}' 
                    AND DATALT = '{$itemTroca->DATALT}' 
                ";

                DB::connection('sqlsrv')->statement($query);

                $query2 = 
                "
                    UPDATE 
                        ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS 
                    SET 
                    ID = {$dado[0]->ID}, 
                    TIPO = 0, 
                    SITUACAO = 1, 
                    OBSERVACAO = '". date("d/m/Y H:i:s")." - Registro Já Processado No Senior (recuperaIdTroca query2)'
                    WHERE PERSONEXTERNALID = '{$itemTroca->IDEXTERNO}' 
                    AND TRANSFERDATE = '{$itemTroca->DATALT}'
                ";

                DB::connection('sqlsrv')->statement($query2);

                var_dump("Update");

            }
        }
    }

    public function recuperaIdTroca2()
    {
        // $query = "SELECT  ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.*,  ".env('DB_OWNER')."FLEX_COLABORADOR.EXTERNALID FROM ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS
        // JOIN  ".env('DB_OWNER')."FLEX_COLABORADOR 
        //     ON   ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD =  ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.NUMCAD
        //     AND  ".env('DB_OWNER')."FLEX_COLABORADOR.TIPCOL =  ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.TIPCOL
        //     AND  ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP =  ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.NUMEMP
        // WHERE  ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO IN (0,2)";

        $query = 
        "
            SELECT 
                ID
            FROM  ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS 
            WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.SITUACAO IN (0,2)
            AND EXISTS (
                SELECT * FROM  ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS
                JOIN  ".env('DB_OWNER')."FLEX_COLABORADOR 
                        ON   ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO =  ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.IDEXTERNO
                WHERE  ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO =  ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.PERSONEXTERNALID
                AND  ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.TRANSFERDATE =  ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.DATALT
            )
        ";

        $sindicatos = DB::connection('sqlsrv')->select($query);

        foreach ($sindicatos as $itemTroca) {

            if ($itemTroca) {

                $query2 = 
                "
                    UPDATE 
                    ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS 
                    SET 
                        ID = {$itemTroca->ID}, 
                        TIPO = 0, 
                        SITUACAO = 1, 
                        OBSERVACAO = '". date("d/m/Y H:i:s")." - Registro Já Processado No Senior (recuperaIdTroca query2)'
                    WHERE ID = {$itemTroca->ID}
                ";


                //var_dump($query2);exit;

                DB::connection('sqlsrv')->statement($query2);

                var_dump("Update");

            }
        }


        // $query6 = 
        // "
        //     UPDATE ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS
        //     SET ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.SITUACAO = 99,
        //     ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.OBSERVACAO = 'Registro Desconsiderado, Colaborador já está Demitido!'
        //     FROM ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS
        //     JOIN ".env('DB_OWNER')."FLEX_COLABORADOR
        //         ON ".env('DB_OWNER')."FLEX_COLABORADOR.EXTERNALID = ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.PERSONEXTERNALID
        //         AND ".env('DB_OWNER')."FLEX_COLABORADOR.SITFUN = 7
        //     WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_SINDICATOS.SITUACAO IN(0,2)
        // ";

        // DB::connection('sqlsrv')->statement($query6);

        $obs7 = date('d/m/Y H:i:s') . ' - Registro já existe no Nexti!';
        $query7 = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS
            SET ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO = 1,
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.OBSERVACAO = '{$obs7}'
            WHERE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.OBSERVACAO LIKE '%Já existe um registro salvo%'
        ";

        DB::connection('sqlsrv')->statement($query7);

        $query8 = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS
            SET ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO = 1
            WHERE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO IN(0,2)
            AND (
                ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.OBSERVACAO LIKE '%Sindicato já vinculado%'
                OR ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.OBSERVACAO LIKE '%Já existe%'
            )
        ";

        DB::connection('sqlsrv')->statement($query8);

        $query9 = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS
            SET ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO = 1
            WHERE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO IN(0,2)
            AND (
                ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.OBSERVACAO LIKE '%Sindicato já vinculado%'
                OR ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.OBSERVACAO LIKE '%já vinculado%'
            )
        ";

        DB::connection('sqlsrv')->statement($query9);
    }

    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = 
        "
            INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
            VALUES (convert(datetime,'{$dataInicio}',120), convert(datetime,'{$dataFim}',120), '{$msg}')
        ";
        
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
        $this->recuperaIdTroca2();
    }
}