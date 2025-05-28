<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helper\LogColaborador;
use DateTime;

class ProcessaExclusoesAuxiliares extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaExclusoesAuxiliares';

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
        $this->query = "";
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

    private function insereTrocaPostosExcluir(){
        $query = 
        "
            INSERT INTO ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES
            SELECT 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.ID,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.LASTUPDATE,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.PERSONEXTERNALID,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.PERSONID,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.TRANSFERDATETIME,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.WORKPLACEEXTERNALID,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.WORKPLACEID,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.REMOVED,
            0 AS TIPO,
            0 AS SITUACAO,
            '' AS OBSERVACAO,
            0 AS INTEGRA_PROTHEUS,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.USERREGISTERID
            FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.SITUACAO IN(0,2)
            AND ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.TIPO = 0
            AND NOT EXISTS(
                SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES
                WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.ID = ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS.ID
            )
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function buscaTrocasPostosExcluir(){
        $query = 
        "
            SELECT 
            ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMEMP,
            ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMCAD,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.ID
            FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR
                ON ".env('DB_OWNER')."NEXTI_COLABORADOR.ID = ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.PERSONID
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.SITUACAO IN(0,2)
            ORDER BY ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.TRANSFERDATETIME DESC
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function enviaExcluirTrocaPostos(){
        $trocas = $this->buscaTrocasPostosExcluir();

        foreach($trocas as $troca){
            $response = $this->restClient->delete("workplacetransfers/{$troca->ID}", [], [])->getResponse();

            \App\Helper\LogColaborador::init()
                ->empresa($troca->NUMEMP)
                ->matricula($troca->NUMCAD)
                ->processo('Exclusão de Troca Postos Auxiliar')
                ->uri("workplacetransfers/" . $troca->ID)
                ->response([
                    'status' => $this->restClient->getResponse()->getStatusCode(),
                    'data' => $this->restClient->getResponseData()
                ])
                ->save();

            if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
                $errors = $this->getResponseErrors();
                $this->info("Problema troca de posto: {$errors}");

                $this->atualizaRegistroComErroTrocaPostos($troca->ID, $errors);
            } else {
                $this->info("Troca de posto excluída com sucesso.");

                $this->atualizaRegistroTrocaPostos($troca->ID);
            }
        }
    }

    private function atualizaRegistroTrocaPostos($id)
    {
        $obs = date('d/m/Y H:i:s') . ' -  Registro Excluído.';

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES 
            SET 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.SITUACAO = 1, 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.OBSERVACAO = '{$obs}'
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.ID = {$id}
        ");
    }

    private function atualizaRegistroComErroTrocaPostos($id, $erros)
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $erros;

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES 
            SET 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.SITUACAO = 2, 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.OBSERVACAO = '{$obs}'
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS_EXCLUSOES_AUXILIARES.ID = {$id}
        ");
    }

    private function insereTrocaEscalasExcluir(){
        $query = 
        "
            INSERT INTO NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES
            SELECT 
            NEXTI_RET_TROCA_ESCALAS.ID,
            NEXTI_RET_TROCA_ESCALAS.LASTUPDATE,
            NEXTI_RET_TROCA_ESCALAS.PERSONEXTERNALID,
            NEXTI_RET_TROCA_ESCALAS.PERSONID,
            NEXTI_RET_TROCA_ESCALAS.ROTATIONCODE,
            NEXTI_RET_TROCA_ESCALAS.TRANSFERDATETIME,
            NEXTI_RET_TROCA_ESCALAS.SCHEDULEEXTERNALID,
            NEXTI_RET_TROCA_ESCALAS.SCHEDULEID,
            NEXTI_RET_TROCA_ESCALAS.ROTATIONID,
            NEXTI_RET_TROCA_ESCALAS.REMOVED,
            0 AS TIPO,
            0 AS SITUACAO,
            '' AS OBSERVACAO, 
            NEXTI_RET_TROCA_ESCALAS.USERREGISTERID,
            0 AS INTEGRA_PROTHEUS,
            NEXTI_RET_TROCA_ESCALAS.DATA_REGISTRADO_PROTHEUS
            FROM NEXTI_RET_TROCA_ESCALAS
            WHERE NEXTI_RET_TROCA_ESCALAS.SITUACAO IN(0,2)
            AND NEXTI_RET_TROCA_ESCALAS.TIPO = 0
            AND NOT EXISTS(
                SELECT * FROM NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES
                WHERE NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.ID = NEXTI_RET_TROCA_ESCALAS.ID
            )
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function buscaTrocasEscalasExcluir(){
        $query = 
        "
            SELECT 
            ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMEMP,
            ".env('DB_OWNER')."NEXTI_COLABORADOR.NUMCAD,
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.ID
            FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR
                ON ".env('DB_OWNER')."NEXTI_COLABORADOR.ID = ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.PERSONID
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.SITUACAO IN(0,2)
            ORDER BY ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.TRANSFERDATETIME DESC
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function enviaExcluirTrocaEscalas(){
        $trocas = $this->buscaTrocasEscalasExcluir();

        foreach($trocas as $troca){
            $response = $this->restClient->delete("scheduletransfers/{$troca->ID}", [], [])->getResponse();

            \App\Helper\LogColaborador::init()
                ->empresa($troca->NUMEMP)
                ->matricula($troca->NUMCAD)
                ->processo('Exclusão de Troca Escalas Auxiliar')
                ->uri("scheduletransfers/" . $troca->ID)
                ->response([
                    'status' => $this->restClient->getResponse()->getStatusCode(),
                    'data' => $this->restClient->getResponseData()
                ])
                ->save();

            if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
                $errors = $this->getResponseErrors();
                $this->info("Problema troca de escala: {$errors}");

                $this->atualizaRegistroComErroTrocaEscalas($troca->ID, $errors);
            } else {
                $this->info("Troca de escala excluída com sucesso.");

                $this->atualizaRegistroTrocaEscalas($troca->ID);
            }
        }
    }

    private function atualizaRegistroTrocaEscalas($id)
    {
        $obs = date('d/m/Y H:i:s') . ' -  Registro Excluído.';

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES 
            SET 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.SITUACAO = 1, 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.OBSERVACAO = '{$obs}'
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.ID = {$id}
        ");
    }

    private function atualizaRegistroComErroTrocaEscalas($id, $erros)
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $erros;

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES 
            SET 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.SITUACAO = 2, 
            ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.OBSERVACAO = '{$obs}'
            WHERE ".env('DB_OWNER')."NEXTI_RET_TROCA_ESCALAS_EXCLUSOES_AUXILIARES.ID = {$id}
        ");
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

        $this->insereTrocaPostosExcluir();
        $this->enviaExcluirTrocaPostos();        
        $this->insereTrocaEscalasExcluir();        
        $this->enviaExcluirTrocaEscalas();        
    }
}