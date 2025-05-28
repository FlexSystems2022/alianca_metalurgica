<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helper\LogColaborador;

class ProcessaTrocaEscalas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTrocaEscalas';

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
            SELECT 
            *
            FROM ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS
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

    private function getTrocaEscalasNovos()
    {
        $where =
        " 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.SITUACAO IN (0,2)
        ";
        $trocaEscalas = DB::connection('sqlsrv')->select($this->query . $where);
        return $trocaEscalas;
    }

    private function enviaTrocaEscalasNovos()
    {
        $trocaEscalas = $this->getTrocaEscalasNovos();
        $nTrocaEscalas = sizeof($trocaEscalas);
        $this->info("Iniciando envio de dados ({$nTrocaEscalas} escalas)");

        foreach ($trocaEscalas as $trocaEscala) {
            $trocaEscalaNexti = [
                'transferDateTime'      => date('dmYHis', strtotime($trocaEscala->DATALT)),
                'personExternalId'      => $trocaEscala->IDEXTERNO,
                'scheduleExternalId'    => $trocaEscala->ESCALA,
                'rotationCode'          => intval($trocaEscala->TURMA)??1
            ];

            //var_dump($trocaEscalaNexti);exit;
            $ret = $this->processaTrocaEscalaNovo($trocaEscalaNexti, $trocaEscala);
            sleep(2);
        }
        $this->info("Total de {$nTrocaEscalas} trocaEscala(s) cadastrados!");
    }

    private function processaTrocaEscalaNovo($trocaEscala, $trocaOriginal)
    {
        $scheduleExternalId = $trocaEscala['scheduleExternalId'];
        $idCol = $trocaEscala['personExternalId'];

        //var_dump($trocaEscala);exit;

        $this->info("Enviando troca de escala: {$scheduleExternalId} para colaborador {$idCol}");

        $response = $this->restClient->post("scheduletransfers", [], [
            'json' => $trocaEscala
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $resposta = $this->restClient->getResponseData();

            $errors = $this->getResponseErrors();
            $this->info("Problema troca de escala: {$scheduleExternalId} para colaborador {$idCol}: {$errors}");
            $idCol = $trocaEscala['personExternalId'];
            $idEscala = $trocaEscala['scheduleExternalId'];
            $turma = $trocaEscala['rotationCode'];
            $datalt = $trocaOriginal->DATALT;

            $this->atualizaRegistroComErro($idEscala, $idCol, $datalt, $turma, 2, $errors);
        } else {
            $this->info("Troca de escala: {$scheduleExternalId} para colaborador {$idCol} enviada com sucesso.");
            
            $responseData = $this->restClient->getResponseData();
            $trocaEscalaInserido = $responseData['value'];
            $id = $trocaEscalaInserido['id'];
            $idCol = $trocaEscala['personExternalId'];
            $idEscala = $trocaEscala['scheduleExternalId'];
            $datalt = $trocaOriginal->DATALT;
            $turma = $trocaEscala['rotationCode'];

            $this->atualizaRegistro($idEscala, $idCol, $datalt, $turma, 1, $id);
        }
    }

    private function atualizaRegistro($idescala, $idcol, $datalt, $turma, $situacao, $id)
    {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS 
            SET ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.ESCALA = '{$idescala}' 
            AND ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.IDEXTERNO = '{$idcol}' 
            AND ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.DATALT = '{$datalt}'
            AND ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.TURMA = '{$turma}'
        ");
    }

    private function atualizaRegistroComErro($idescala, $idcol, $datalt, $turma, $situacao, $obs = '')
    {   
        $obs = str_replace("'", '', $obs);
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS 
            SET ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.ESCALA = '{$idescala}' 
            AND ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.IDEXTERNO = '{$idcol}' 
            AND ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.DATALT = '{$datalt}'
            AND ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.TURMA = '{$turma}'
        ");
    }

    private function getTrocaEscalasExcluir()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.TIPO = 3 
            AND ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS.SITUACAO IN(0,2)
        ";
        $trocaEscalas = DB::connection('sqlsrv')->select($this->query . $where);

        return $trocaEscalas;
    }

    private function processaTrocaEscalasExcluir()
    {
        $trocaEscalas = $this->getTrocaEscalasExcluir();
        $nTrocaEscalas = sizeof($trocaEscalas);
        $this->info("Iniciando exclusão de dados ({$nTrocaEscalas} trocas de escalas)");

        foreach ($trocaEscalas as $trocaEscala) {
            $trocaEscalaNexti = [
                'transferDateTime'  => date("dmY", strtotime($trocaEscala->DATALT)) . '000001',
                'personExternalId'  => $trocaEscala->IDEXTERNO,
                'scheduleExternalId'=> $trocaEscala->IDEXTERNOESCALA,
                'rotationCode'      => $trocaEscala->TURMA,
                'id'                => $trocaEscala->ID
            ];
            $ret = $this->enviaTrocaEscalaExcluir($trocaEscalaNexti, $trocaEscala);
            sleep(2);
        }
        $this->info("Total de {$nTrocaEscalas} trocaEscala(s) cadastrados!");
    }

    // private function processaTrocaEscalasExcluir()
    // {
    //     $trocaEscalas = $this->getTrocaEscalasExcluir();
    //     $nTrocaEscalas = sizeof($trocaEscalas);
    //     $this->info("Iniciando exclusão de dados ({$nTrocaEscalas} trocas de escalas)");

    //     foreach ($trocaEscalas as $trocaEscala) {
    //         $trocaEscalaNexti = [
    //             'id' => $trocaEscala->ID
    //         ];
    //         $ret = $this->enviaTrocaEscalaExcluir($trocaEscalaNexti, $trocaEscala);
    //         sleep(2);
    //     }
    //     $this->info("Total de {$nTrocaEscalas} trocaEscala(s) cadastrados!");
    // }

    private function enviaTrocaEscalaExcluir($trocaEscala, $trocaOriginal)
    {
        // dd($trocaOriginal);
        $scheduleExternalId = $trocaOriginal->IDEXTERNO;
        $idCol = $trocaOriginal->NUMCAD;

        $this->info("Excluindo troca de escala: {$scheduleExternalId} para colaborador {$idCol}");

        $response = $this->restClient->delete("scheduletransfers/" . $trocaEscala['id'], [], [])->getResponse();
        
        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema troca de escala: {$scheduleExternalId} para colaborador {$idCol}: {$errors}");
            $idCol = $trocaOriginal->NUMCAD;
            $idEscala = $trocaOriginal->ESCALA;
            $datalt = $trocaOriginal->DATALT;
            $turma = $trocaOriginal->TURMA;

            $this->atualizaRegistroComErro($idEscala, $idCol, $datalt, $turma, 2, $errors);
        } else {
            $this->info("Exclusão de troca de escala: {$scheduleExternalId} para colaborador {$idCol} enviada com sucesso.");
            
            $responseData = $this->restClient->getResponseData();

            $trocaEscalaInserido = $responseData['value'];
            $id = $trocaEscalaInserido['id'];
            $idCol = $trocaOriginal->NUMCAD;
            $idEscala = $trocaOriginal->ESCALA;
            $datalt = $trocaOriginal->DATALT;
            $turma = $trocaOriginal->TURMA;

            $this->atualizaRegistro($idEscala, $idCol, $datalt, $turma, 1, $trocaEscala['id']);
        }
    }

    // ANALISAR
    // private function enviaTrocaEscalaExcluir($trocaEscala, $trocaOriginal)
    // {
    //     $scheduleExternalId = $trocaEscala['scheduleExternalId'];
    //     $idCol = $trocaEscala['personExternalId'];

    //     $this->info("Excluindo troca de escala: {$scheduleExternalId} para colaborador {$idCol}");

    //     $response = $this->restClient->delete("scheduletransfers/" . $trocaEscala['id'], [], [])->getResponse();
        
    //     if (!$this->restClient->isResponseStatusCode(200)) {
    //         $errors = $this->getResponseErrors();
    //         $this->info("Problema troca de escala: {$scheduleExternalId} para colaborador {$idCol}: {$errors}");
    //         $idCol = $trocaEscala['personExternalId'];
    //         $idEscala = $trocaEscala['scheduleExternalId'];
    //         $datalt = $trocaOriginal->DATALT;
    //         $turma = $trocaEscala['rotationCode'];

    //         $this->atualizaRegistroComErro($trocaOriginal->ESCALA, $idCol, $datalt, $trocaOriginal->TURMA, 2, $errors);
    //     } else {
    //         $this->info("Exclusão de troca de escala: {$scheduleExternalId} para colaborador {$idCol} enviada com sucesso.");
            
    //         $responseData = $this->restClient->getResponseData();

    //         $trocaEscalaInserido = $responseData['value'];
    //         $id = $trocaEscalaInserido['id'];
    //         $idCol = $trocaEscala['personExternalId'];
    //         $idEscala = $trocaEscala['scheduleExternalId'];
    //         $datalt = $trocaOriginal->DATALT;
    //         $turma = $trocaEscala['rotationCode'];

    //         $this->atualizaRegistro($trocaOriginal->ESCALA, $idCol, $datalt, $trocaOriginal->TURMA, 1, $trocaEscala['id']);
    //     }
    // }

    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',120), convert(datetime,'{$dataFim}',120), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    public function corrigeRegistrosDiferentesNextiFlex()
    {
        $query =
        "
            SELECT
                * 
            FROM ".env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS
            WHERE SITUACAO = 1 AND TIPO = 3
        ";

        $registros = DB::connection('sqlsrv')->select($query);
        if (sizeof($registros) > 0) {
            foreach ($registros as $itemRegistro) {
                //if (explode(' ', $itemRegistro->fimSenior)[0] != explode(' ', $itemRegistro->fimNexti)[0]) {
                    $update ="DELETE FROM " .env('DB_OWNER')."FLEX_TROCA_ESCALA_PROTHEUS WHERE ID = {$itemRegistro->ID}";
                    DB::connection('sqlsrv')->statement($update);

                    $delete = "DELETE FROM " .env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS WHERE ID = {$itemRegistro->ID}";
                    DB::connection('sqlsrv')->statement($delete);
                //}
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
        $dataInicio = date('Y-m-d H:i:s A');
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');


        $this->corrigeRegistrosDiferentesNextiFlex();
        $this->enviaTrocaEscalasNovos();
        // $this->processaTrocaEscalasExcluir();
    }
}
