<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaTrocaEscalasAux2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTrocaEscalasAux2';

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
            FROM ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX
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
            WHERE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TIPO = 0 
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO IN (99)
            ORDER BY ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT DESC
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

        \App\Helper\LogColaborador::init()
            ->empresa($trocaOriginal->NUMEMP)
            ->matricula($trocaOriginal->NUMCAD)
            ->processo('Criação de Troca Escalas')
            ->uri("scheduletransfers")
            ->payload($trocaEscala)
            ->response([
                'status' => $this->restClient->getResponse()->getStatusCode(),
                'data' => $this->restClient->getResponseData()
            ])
            ->save();

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
            UPDATE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX 
            SET ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ESCALA = '{$idescala}' 
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.IDEXTERNO = '{$idcol}' 
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT = '{$datalt}'
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TURMA = '{$turma}'
        ");
    }

    private function atualizaRegistroComErro($idescala, $idcol, $datalt, $turma, $situacao, $obs = '')
    {   
        $obs = str_replace("'", '', $obs);
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX 
            SET ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.SITUACAO = 88, 
            ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.ESCALA = '{$idescala}' 
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.IDEXTERNO = '{$idcol}' 
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.DATALT = '{$datalt}'
            AND ".env('DB_OWNER')."NEXTI_TROCA_ESCALA_PROTHEUS_AUX.TURMA = '{$turma}'
        ");
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

        $this->enviaTrocaEscalasNovos();
    }
}
