<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class ProcessaTrocaSindicatos extends Command
{
    protected $signature = 'ProcessaTrocaSindicatos';
    
    protected $description = 'Command description';

    private $restClient = null;

    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');

        parent::__construct();
        $this->query =  "
            SELECT 
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.*,
            ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO AS IDEXTERNOCOLAB, 
            ".env('DB_OWNER')."FLEX_COLABORADOR.ID AS PERSONID, 
            ".env('DB_OWNER')."FLEX_COLABORADOR.NOMFUN,
            ".env('DB_OWNER')."FLEX_SINDICATOS.NOMSIN AS TRADEUNIONNAME,
            ".env('DB_OWNER')."FLEX_SINDICATOS.ID AS TRADEUNIONID,
            ".env('DB_OWNER')."FLEX_SINDICATOS.IDEXTERNO AS TRADEUNIONEXTERNALID
            FROM ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS
            JOIN ".env('DB_OWNER')."FLEX_COLABORADOR 
                ON ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO = ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.IDEXTERNO
            JOIN ".env('DB_OWNER')."FLEX_SINDICATOS
                ON ".env('DB_OWNER')."FLEX_SINDICATOS.CODSIN = ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.CODSIN
        ";
    }


    private function getTrocaSindicatosNovos()
    {
        $where =
        "   
            WHERE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.TIPO = 0 
            AND (".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO = 0 
            OR ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO = 2)
        ";

        $trocaEscalas = DB::connection('sqlsrv')->select($this->query . $where);

        return $trocaEscalas;
    }

    private function buscaTrocaSindicatosNovos()
    {
        $trocaSindicato = $this->getTrocaSindicatosNovos();
        $nTrocaSindicato = sizeof($trocaSindicato);
        $this->info("Iniciando envio de dados ({$nTrocaSindicato} trocas de sindicato)");

        foreach ($trocaSindicato as $trocaSindicatoItem) {
            //var_dump($trocaSindicatoItem);exit;
            $trocaSindicatoNexti = [
                'personExternalId'      => $trocaSindicatoItem->IDEXTERNOCOLAB,
                'personId'              => $trocaSindicatoItem->PERSONID,
                'personName'            => $trocaSindicatoItem->NOMFUN,
                'tradeUnionExternalId'  => $trocaSindicatoItem->TRADEUNIONEXTERNALID,
                'tradeUnionId'          => $trocaSindicatoItem->TRADEUNIONID,
                'tradeUnionName'        => $trocaSindicatoItem->TRADEUNIONNAME,
                'transferDate'          =>implode('', array_reverse(explode('-', explode(' ', $trocaSindicatoItem->DATALT)[0]))) . '000000'
            ];

            $ret = $this->processaTrocaSindicatoNovo($trocaSindicatoNexti, $trocaSindicatoItem->IDEXTERNO);
            //sleep(2);
        }

        $this->info("Total de {$nTrocaSindicato} troca de sindicato(s) cadastrados!");
    }

    public function formatarDataServer()
    {
        //return implode('', array_reverse(explode('-', explode(' ', $data)[0]))) . ' ' . explode(delimiter, string)
    }

    private function processaTrocaSindicatoNovo($trocaSindicato, $idExterno)
    {
        $this->info("Enviando troca de sindicato para o funcionário {$trocaSindicato['personName']}");
        
        $response = $this->restClient->post("tradeunionstransfers", [], [
            'json' => $trocaSindicato
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $resposta = $this->restClient->getResponseData();

            $errors = $this->getResponseErrors();
            $this->info("Problema troca de sindicato {$trocaSindicato['tradeUnionExternalId']} para colaborador {$trocaSindicato['personId']}: {$errors}");
            
            $trocaSindicato['transferDate'] = DateTime::createFromFormat('dmYHis', $trocaSindicato['transferDate'])->format('Y-m-d H:i:s') . '.000';

            $this->atualizaRegistroComErro($trocaSindicato['tradeUnionId'], $trocaSindicato['personId'], $trocaSindicato['transferDate'], 2, $errors, $idExterno);
        } else {
            $this->info("Troca de sindicato: {$trocaSindicato['tradeUnionExternalId']}  para colaborador {$trocaSindicato['personId']} enviada com sucesso.");
            
            $responseData = $this->restClient->getResponseData();
            $id = $responseData['value']['id'];
            $data = DateTime::createFromFormat('dmYHis', $trocaSindicato['transferDate'])->format('Y-m-d H:i:s') . '.000';
            $this->atualizaRegistro($trocaSindicato['tradeUnionId'], $trocaSindicato['personId'], $data, 1, $id, $idExterno);
        }
    }

    private function atualizaRegistro($tradeUnionId, $personId, $transferDate, $situacao, $id, $idExterno)
    {
        $obs = date('d/m/Y H:i:s') . ' -  Registro enviado.';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS 
            SET ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.TIPO = 1, 
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.IDEXTERNO = '{$idExterno}'  
            AND ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.DATALT = CONVERT(DATETIME, '{$transferDate}', 120)
        ");
    }

    private function atualizaRegistroComErro($tradeUnionId, $personId, $transferDate, $situacao, $obs = '', $idExterno)
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS 
            SET ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.IDEXTERNO = '{$idExterno}' 
            AND ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.DATALT = CONVERT(DATETIME, '{$transferDate}', 120)
        ");
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


    private function getTrocaSindicatoExcluir()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.TIPO = 3 
            AND ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS.SITUACAO IN(0,2)
        ";

        $trocaEscalas = DB::connection('sqlsrv')->select($this->query . $where);

        return $trocaEscalas;
    }


    private function processaTrocaSindicatoExcluir()
    {
        $trocaSindicato = $this->getTrocaSindicatoExcluir();
        $nTrocaSindicato = sizeof($trocaSindicato);
        $this->info("Iniciando exclusão de dados ({$nTrocaSindicato} trocas de escalas)");

        foreach ($trocaSindicato as $trocaSindicatoItem) {
            $this->enviaTrocaSindicatoExcluir($trocaSindicatoItem);
        }
        $this->info("Total de {$nTrocaSindicato} troca sindicato(s) cadastrados!");
    }

    public function enviaTrocaSindicatoExcluir($trocaSindicato)
    {
        $this->info("Excluindo troca de sindicato: {$trocaSindicato->ID}");

        $response = $this->restClient->delete("tradeunionstransfers/{$trocaSindicato->ID}", [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();

            $this->info("Problema excluir troca sindicado {$trocaSindicato->ID}: {$errors}");

            $this->atualizaRegistroComErro($trocaSindicato->TRADEUNIONID, $trocaSindicato->PERSONID, $trocaSindicato->DATALT, 2, $errors, $trocaSindicato->IDEXTERNO);
        } else {
            $this->info("Exclusão de troca de sindicato: {$trocaSindicato->ID} enviada com sucesso.");
            $this->removeRegistroById($trocaSindicato->ID);
        }
    }

    public function removeRegistroById($id)
    {
        DB::connection('sqlsrv')->statement("DELETE ".env('DB_OWNER')."FLEX_TROCA_SINDICATO_PROTHEUS WHERE ID = {$id}");
    }

    public function handle()
    {
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->processaTrocaSindicatoExcluir();
        $this->buscaTrocaSindicatosNovos();    }
}
